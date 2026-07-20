<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\PaymentStatusEnum;
use App\Enums\PledgeFrequencyEnum;
use App\Enums\PledgeInstallmentStatusEnum;
use App\Enums\PledgeStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Mail\PaymentReceiptMail;
use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\PledgeInstallment;
use App\Models\PledgePayment;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeInstallmentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgePaymentRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Theme\ThemeResolver;
use App\Services\ThirdParty\Payment\PaymentGatewayService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PledgeService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
        private readonly PledgeInstallmentRepositoryInterface $installmentRepository,
        private readonly PledgePaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {}

    /**
     * @param  ?User  $user  Null for a guest pledge (BRD ENT-04) — $validated must then carry
     *                       `full_name` and `email` (see GuestMakePledgeRequest).
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function createPledge(?User $user, array $validated, Request $request): array
    {
        $campaign = $this->campaignRepository->findActiveByUuid($validated['campaign_uuid']);

        if (! $campaign instanceof Campaign) {
            throw new ApiException('Campaign not found.', 404);
        }

        if (! $campaign->isOpenForDonations()) {
            throw new ApiException('This campaign is not currently accepting donations.', 422);
        }

        $frequency = PledgeFrequencyEnum::from($validated['frequency']);

        if (! $frequency->isRecurring() && ! $campaign->allow_one_time) {
            throw new ApiException('This campaign does not accept one-time donations.', 422);
        }

        if ($frequency->isRecurring() && ! $campaign->allow_recurring) {
            throw new ApiException('This campaign does not accept recurring pledges.', 422);
        }

        $anchorDate = $frequency->isRecurring() ? (string) $validated['start_date'] : (string) $validated['expected_payment_date'];
        $installmentCount = $frequency->isRecurring()
            ? $this->deriveInstallmentCount(Carbon::parse($validated['start_date']), Carbon::parse($validated['end_date']), $frequency)
            : 1;
        $schedule = $this->buildInstallmentSchedule((float) $validated['total_amount'], $installmentCount, $frequency, $anchorDate);

        return DB::transaction(function () use ($user, $campaign, $validated, $frequency, $installmentCount, $schedule, $request): array {
            $pledge = $this->pledgeRepository->create([
                'campaign_id' => $campaign->id,
                'user_id' => $user?->id,
                'guest_name' => $user === null ? $validated['full_name'] : null,
                'guest_email' => $user === null ? $validated['email'] : null,
                'frequency' => $frequency->value,
                'currency' => $campaign->currency,
                'total_amount' => $validated['total_amount'],
                'amount_paid' => 0,
                'installment_count' => $installmentCount,
                'status' => PledgeStatusEnum::PENDING->value,
                'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
                'start_date' => $frequency->isRecurring() ? $validated['start_date'] : null,
                'end_date' => $frequency->isRecurring() ? $validated['end_date'] : null,
                'next_installment_due_at' => $schedule[0]['due_date'],
            ]);

            foreach ($schedule as $item) {
                $this->installmentRepository->create([
                    'pledge_id' => $pledge->id,
                    'sequence' => $item['sequence'],
                    'amount' => $item['amount'],
                    'currency' => $campaign->currency,
                    'due_date' => $item['due_date'],
                ]);
            }

            $firstInstallment = $this->installmentRepository->firstByPledge($pledge);

            if (! $firstInstallment instanceof PledgeInstallment) {
                throw new ApiException('Unable to schedule the first installment.', 500);
            }

            $initialization = $this->initializePaymentFor($user, $pledge, $firstInstallment, $validated['payment_method'] ?? null, $validated['email'] ?? null);

            $donorName = $user?->displayName() ?? $validated['full_name'];

            GeneralHelper::storeAuditLog(
                $user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::PLEDGE_CREATED,
                $request,
                $user?->uuid,
                [
                    'campaign_uuid' => $campaign->uuid,
                    'frequency' => $frequency->value,
                    'total_amount' => $validated['total_amount'],
                    'guest' => $user === null,
                    'donor_email' => $pledge->donorEmail(),
                ],
                $donorName.' created a pledge for '.$campaign->title.'.',
                Pledge::class,
                $pledge->uuid,
                ModuleEnums::fundraising,
                201,
            );

            return [
                'pledge' => $this->pledgeRepository->loadFresh($pledge, ['user', 'campaign', 'installments', 'payments']),
                'authorization_url' => $initialization['authorization_url'],
                'reference' => $initialization['reference'],
            ];
        });
    }

    /**
     * Initializes payment for the next due installment of an existing pledge (used for
     * recurring pledges after the first installment, or to retry a failed payment).
     *
     * @return array<string, mixed>
     */
    public function payInstallment(User $user, string $pledgeUuid, string $installmentUuid, Request $request, ?string $paymentMethod = null): array
    {
        $pledge = $this->findForUser($user, $pledgeUuid);

        if (in_array($pledge->status, [PledgeStatusEnum::CANCELLED->value, PledgeStatusEnum::COMPLETED->value], true)) {
            throw new ApiException('This pledge is no longer accepting payments.', 422);
        }

        $installment = $this->installmentRepository->findByUuidForPledge($pledge, $installmentUuid);

        if (! $installment instanceof PledgeInstallment) {
            throw new ApiException('Installment not found.', 404);
        }

        if ($installment->status === PledgeInstallmentStatusEnum::PAID->value) {
            throw new ApiException('This installment has already been paid.', 422);
        }

        $initialization = $this->initializePaymentFor($user, $pledge, $installment, $paymentMethod);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::PAYMENT_INITIATED,
            $request,
            $user->uuid,
            ['pledge_uuid' => $pledge->uuid, 'installment_uuid' => $installment->uuid],
            $user->displayName().' initiated payment for installment #'.$installment->sequence.'.',
            PledgeInstallment::class,
            $installment->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $initialization;
    }

    /**
     * The one place that actually confirms money moved. Called from two different entry
     * points — PledgeController::verifyPayment() (customer/frontend, after the Paystack
     * redirect) and PaystackWebhookController (Paystack calling us directly) — and it doesn't
     * matter which gets here first: the early-return below makes a second call for an
     * already-settled payment a harmless no-op.
     *
     * @return array<string, mixed>
     */
    public function verifyPayment(string $reference, Request $request): array
    {
        $payment = $this->paymentRepository->findByReference($reference);

        if (! $payment instanceof PledgePayment) {
            throw new ApiException('Payment not found.', 404);
        }

        // Already settled by the other entry point (webhook vs frontend verify) — nothing to do.
        if ($payment->status === PaymentStatusEnum::SUCCESSFUL->value) {
            return ['payment' => $payment, 'pledge' => $payment->pledge];
        }

        $result = $this->paymentGatewayService->verify($reference);

        if ($result['status'] !== 'successful') {
            $payment = $this->paymentRepository->markFailed($payment);

            GeneralHelper::storeAuditLog(
                $payment->user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::PAYMENT_FAILED,
                $request,
                $payment->user?->uuid,
                ['reference' => $reference, 'gateway_status' => $result['status'], 'donor_email' => $payment->pledge->donorEmail()],
                'Payment for pledge installment failed or was not completed.',
                PledgePayment::class,
                $payment->uuid,
                ModuleEnums::fundraising,
                200,
            );

            return ['payment' => $payment, 'pledge' => $payment->pledge];
        }

        return DB::transaction(function () use ($payment, $result, $reference, $request): array {
            $payment = $this->paymentRepository->markSuccessful($payment, [
                'paid_at' => now(),
                'card_last_four' => $result['card_last_four'],
                'meta' => array_filter([
                    'channel' => $result['channel'],
                    'gateway_amount' => $result['amount'],
                    'gateway_currency' => $result['currency'],
                ]),
            ]);

            $installment = $payment->installment;
            $pledge = $payment->pledge;
            $campaign = $pledge->campaign;

            if ($installment instanceof PledgeInstallment) {
                $this->installmentRepository->markPaid($installment);
            }

            $pledge = $this->pledgeRepository->incrementAmountPaid($pledge, (string) $payment->amount);

            $nextDue = $this->installmentRepository->nextPending($pledge);
            $pledgeCompleted = $nextDue === null;

            $pledge = $this->pledgeRepository->update($pledge, [
                'status' => $pledgeCompleted ? PledgeStatusEnum::COMPLETED->value : PledgeStatusEnum::ON_TRACK->value,
                'next_installment_due_at' => $nextDue?->due_date,
                'completed_at' => $pledgeCompleted ? now() : null,
            ]);

            $campaign = $this->campaignRepository->incrementRaisedAmount($campaign, (string) $payment->amount);

            $userType = $payment->user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER;

            GeneralHelper::storeAuditLog(
                $userType,
                AuditActionEnum::PAYMENT_SUCCEEDED,
                $request,
                $payment->user?->uuid,
                ['reference' => $reference, 'amount' => (string) $payment->amount, 'donor_email' => $pledge->donorEmail()],
                'Payment for pledge installment succeeded.',
                PledgePayment::class,
                $payment->uuid,
                ModuleEnums::fundraising,
                200,
            );

            if ($pledgeCompleted) {
                GeneralHelper::storeAuditLog(
                    $userType,
                    AuditActionEnum::PLEDGE_COMPLETED,
                    $request,
                    $payment->user?->uuid,
                    ['pledge_uuid' => $pledge->uuid],
                    'Pledge fully paid.',
                    Pledge::class,
                    $pledge->uuid,
                    ModuleEnums::fundraising,
                    200,
                );
            }

            $this->notifyPaymentSucceeded($pledge, $campaign, $reference);

            return [
                'payment' => $payment,
                'pledge' => $this->pledgeRepository->loadFresh($pledge, ['user', 'campaign', 'installments']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->pledgeRepository->paginateForUser($user->id, $filters, $perPage);
    }

    public function findForUser(User $user, string $uuid): Pledge
    {
        $pledge = $this->pledgeRepository->findByUuidForUser($user->id, $uuid);

        if (! $pledge instanceof Pledge) {
            throw new ApiException('Pledge not found.', 404);
        }

        return $pledge;
    }

    /**
     * Number of installments that fit between start_date and end_date at the given frequency's
     * interval (e.g. monthly over 3 calendar months = 3 installments: start, +1mo, +2mo).
     */
    private function deriveInstallmentCount(Carbon $startDate, Carbon $endDate, PledgeFrequencyEnum $frequency): int
    {
        $count = intdiv($startDate->diffInMonths($endDate), $frequency->intervalMonths()) + 1;

        if ($count < 2) {
            throw new ApiException('The selected end date is too soon for a '.$frequency->value.' pledge; choose a later end date.', 422);
        }

        if ($count > 60) {
            throw new ApiException('The selected date range is too long; choose a later start date, an earlier end date, or a less frequent interval.', 422);
        }

        return $count;
    }

    /**
     * @return array{sequence: int, amount: string, due_date: string}[]
     */
    private function buildInstallmentSchedule(float $totalAmount, int $installmentCount, PledgeFrequencyEnum $frequency, string $anchorDate): array
    {
        $base = round($totalAmount / $installmentCount, 2);
        $schedule = [];
        $anchor = Carbon::parse($anchorDate);

        for ($sequence = 1; $sequence <= $installmentCount; $sequence++) {
            $amount = $sequence === $installmentCount
                ? round($totalAmount - $base * ($installmentCount - 1), 2)
                : $base;

            $schedule[] = [
                'sequence' => $sequence,
                'amount' => number_format($amount, 2, '.', ''),
                'due_date' => $anchor->copy()->addMonths($frequency->intervalMonths() * ($sequence - 1))->toDateString(),
            ];
        }

        return $schedule;
    }

    /**
     * @return array{authorization_url: string, access_code: ?string, reference: string}
     */
    /**
     * Kicks off payment for one installment: mints our own reference, asks the gateway for a
     * checkout link (PaymentGatewayService::initialize()), and records a *pending*
     * PledgePayment row so verifyPayment() has something to find later by this reference.
     * Nothing is marked paid here — that only happens once the gateway confirms it.
     */
    private function initializePaymentFor(?User $user, Pledge $pledge, PledgeInstallment $installment, ?string $paymentMethod, ?string $guestEmail = null): array
    {
        $reference = 'PLG_'.strtoupper(Str::random(20));
        $email = $user->email ?? $guestEmail;

        $initialization = $this->paymentGatewayService->initialize(
            $reference,
            (string) $installment->amount,
            $pledge->currency,
            $email,
            ['pledge_uuid' => $pledge->uuid, 'installment_uuid' => $installment->uuid],
        );

        $this->paymentRepository->create([
            'pledge_id' => $pledge->id,
            'pledge_installment_id' => $installment->id,
            'user_id' => $user?->id,
            'amount' => $installment->amount,
            'currency' => $pledge->currency,
            'method' => $paymentMethod,
            'gateway' => 'paystack',
            'gateway_reference' => $initialization['reference'],
            'status' => PaymentStatusEnum::PENDING->value,
        ]);

        return $initialization;
    }

    /**
     * Guests have no account to hold an in-app notification, so they get a direct email
     * receipt instead of {@see GenericDatabaseNotification} (which requires a Notifiable model).
     */
    private function notifyPaymentSucceeded(Pledge $pledge, Campaign $campaign, string $reference): void
    {
        if ($pledge->isGuest()) {
            $this->sendGuestReceipt($pledge, $campaign, $reference);

            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::fundraising->value,
            event: 'payment_succeeded',
            title: 'Payment received',
            message: 'Thank you! Your payment of '.$pledge->currency.' '.number_format((float) $pledge->amount_paid, 2).' towards "'.$campaign->title.'" was received.',
            meta: ['pledge_uuid' => $pledge->uuid, 'campaign_uuid' => $campaign->uuid],
            sendMail: true,
            mailSubject: 'Payment received - '.$campaign->title,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$pledge->user->uuid], $notification);
    }

    private function sendGuestReceipt(Pledge $pledge, Campaign $campaign, string $reference): void
    {
        try {
            $theme = app(ThemeResolver::class)->resolveForMail();

            Mail::to($pledge->guest_email)->send(new PaymentReceiptMail(
                $pledge->guest_name,
                $theme,
                $campaign->title,
                Money::format($pledge->amount_paid, $pledge->currency),
                $reference,
            ));
        } catch (\Throwable $th) {
            Log::warning('Guest payment receipt email failed.', [
                'pledge_uuid' => $pledge->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }
}
