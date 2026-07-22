<?php

namespace App\Services\Fundraising;

use App\Enums\AuditActionEnum;
use App\Enums\DonationFrequencyEnum;
use App\Enums\DonationStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\PaymentStatusEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Mail\PaymentReceiptMail;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Campaign\CampaignRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Settings\PaymentMethodService;
use App\Services\Theme\ThemeResolver;
use App\Services\ThirdParty\Payment\PaymentGatewayService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DonationService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaignRepository,
        private readonly DonationRepositoryInterface $donationRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly PaymentGatewayService $paymentGatewayService,
        private readonly NotificationDispatchService $notificationDispatchService,
        private readonly PaymentMethodService $paymentMethodService,
        private readonly DonorTierRepositoryInterface $donorTierRepository,
    ) {}

    /**
     * @param  ?User  $user  Null for a guest donation (BRD ENT-04); $validated must then carry
     *                       `full_name` and `email` (see GuestMakeDonationRequest).
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function createDonation(?User $user, array $validated, Request $request): array
    {
        $campaign = $this->campaignRepository->findActiveByUuid($validated['campaign_uuid']);

        if (! $campaign instanceof Campaign) {
            throw new ApiException('Campaign not found.', 404);
        }

        if (! $campaign->isOpenForDonations()) {
            throw new ApiException('This campaign is not currently accepting donations.', 422);
        }

        $frequency = DonationFrequencyEnum::from($validated['frequency']);

        if (! $frequency->isRecurring() && ! $campaign->allow_one_time) {
            throw new ApiException('This campaign does not accept one-time donations.', 422);
        }

        if ($frequency->isRecurring() && ! $campaign->allow_recurring) {
            throw new ApiException('This campaign does not accept recurring donations.', 422);
        }

        return DB::transaction(function () use ($user, $campaign, $validated, $frequency, $request): array {
            $donation = $this->donationRepository->create([
                'campaign_id' => $campaign->id,
                'user_id' => $user?->id,
                'guest_name' => $user === null ? $validated['full_name'] : null,
                'guest_email' => $user === null ? $validated['email'] : null,
                'frequency' => $frequency->value,
                'currency' => $campaign->currency,
                'amount' => $validated['amount'],
                'total_received' => 0,
                'status' => DonationStatusEnum::PENDING->value,
                'is_anonymous' => (bool) ($validated['is_anonymous'] ?? false),
                'next_charge_at' => null,
            ]);

            $initialization = $this->initializePaymentFor($user, $donation, $validated['payment_method'] ?? null, $validated['email'] ?? null);

            $donorName = $user?->displayName() ?? $validated['full_name'];

            GeneralHelper::storeAuditLog(
                $user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::DONATION_CREATED,
                $request,
                $user?->uuid,
                [
                    'campaign_uuid' => $campaign->uuid,
                    'frequency' => $frequency->value,
                    'amount' => $validated['amount'],
                    'guest' => $user === null,
                    'donor_email' => $donation->donorEmail(),
                ],
                $donorName.' made a donation to '.$campaign->title.'.',
                Donation::class,
                $donation->uuid,
                ModuleEnums::fundraising,
                201,
            );

            return [
                'donation' => $this->donationRepository->loadFresh($donation, ['user', 'campaign', 'payments']),
                'authorization_url' => $initialization['authorization_url'],
                'reference' => $initialization['reference'],
            ];
        });
    }

    /**
     * Charges the next cycle of an active recurring donation (the donor manually triggers each
     * cycle, or retries a failed one); the donation equivalent of PledgeService::payInstallment().
     *
     * @return array<string, mixed>
     */
    public function chargeNextCycle(User $user, string $donationUuid, Request $request, ?string $paymentMethod = null): array
    {
        $donation = $this->findForUser($user, $donationUuid);

        if (! $donation->isRecurring()) {
            throw new ApiException('This is a one-time donation and cannot be charged again.', 422);
        }

        if (in_array($donation->status, [DonationStatusEnum::CANCELLED->value, DonationStatusEnum::FAILED->value, DonationStatusEnum::PAUSED->value], true)) {
            throw new ApiException('This donation is no longer accepting payments.', 422);
        }

        $initialization = $this->initializePaymentFor($user, $donation, $paymentMethod);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::PAYMENT_INITIATED,
            $request,
            $user->uuid,
            ['donation_uuid' => $donation->uuid],
            $user->displayName().' initiated the next donation cycle for "'.$donation->campaign->title.'".',
            Donation::class,
            $donation->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $initialization;
    }

    /**
     * Pauses an active recurring donation; no further cycles can be charged until resumed.
     */
    public function pauseDonation(User $user, string $donationUuid, Request $request): Donation
    {
        $donation = $this->findForUser($user, $donationUuid);

        if (! $donation->isRecurring() || $donation->status !== DonationStatusEnum::ACTIVE->value) {
            throw new ApiException('Only an active recurring donation can be paused.', 422);
        }

        $donation = $this->donationRepository->update($donation, [
            'status' => DonationStatusEnum::PAUSED->value,
            'paused_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::DONATION_PAUSED,
            $request,
            $user->uuid,
            ['donation_uuid' => $donation->uuid],
            $user->displayName().' paused a recurring donation to "'.$donation->campaign->title.'".',
            Donation::class,
            $donation->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $donation;
    }

    /**
     * Resumes a paused recurring donation; the donor can charge its next cycle again.
     */
    public function resumeDonation(User $user, string $donationUuid, Request $request): Donation
    {
        $donation = $this->findForUser($user, $donationUuid);

        if ($donation->status !== DonationStatusEnum::PAUSED->value) {
            throw new ApiException('Only a paused donation can be resumed.', 422);
        }

        $donation = $this->donationRepository->update($donation, [
            'status' => DonationStatusEnum::ACTIVE->value,
            'paused_at' => null,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::DONATION_RESUMED,
            $request,
            $user->uuid,
            ['donation_uuid' => $donation->uuid],
            $user->displayName().' resumed a recurring donation to "'.$donation->campaign->title.'".',
            Donation::class,
            $donation->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $donation;
    }

    /**
     * Cancels a recurring donation for good; unlike pause, this cannot be undone by resuming.
     */
    public function cancelDonation(User $user, string $donationUuid, Request $request): Donation
    {
        $donation = $this->findForUser($user, $donationUuid);

        if (! $donation->isRecurring() || ! in_array($donation->status, [DonationStatusEnum::ACTIVE->value, DonationStatusEnum::PAUSED->value], true)) {
            throw new ApiException('Only an active or paused recurring donation can be cancelled.', 422);
        }

        $donation = $this->donationRepository->update($donation, [
            'status' => DonationStatusEnum::CANCELLED->value,
            'cancelled_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::DONATION_CANCELLED,
            $request,
            $user->uuid,
            ['donation_uuid' => $donation->uuid],
            $user->displayName().' cancelled a recurring donation to "'.$donation->campaign->title.'".',
            Donation::class,
            $donation->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $donation;
    }

    /**
     * Changes the amount and/or frequency of an active or paused recurring donation. This
     * doesn't charge anything; it only changes what the next "Charge next cycle" call will
     * use. Frequency can only change between recurring cadences (never to one_time; use
     * cancelDonation() to stop recurring altogether).
     *
     * @param  array{amount?: numeric-string|float, frequency?: string}  $validated
     */
    public function modifyDonation(User $user, string $donationUuid, array $validated, Request $request): Donation
    {
        $donation = $this->findForUser($user, $donationUuid);

        if (! $donation->isRecurring() || ! in_array($donation->status, [DonationStatusEnum::ACTIVE->value, DonationStatusEnum::PAUSED->value], true)) {
            throw new ApiException('Only an active or paused recurring donation can be modified.', 422);
        }

        $updates = [];

        if (array_key_exists('amount', $validated)) {
            $updates['amount'] = $validated['amount'];
        }

        if (array_key_exists('frequency', $validated) && $validated['frequency'] !== $donation->frequency) {
            $newFrequency = DonationFrequencyEnum::from($validated['frequency']);
            $updates['frequency'] = $newFrequency->value;
            // Charging is donor-triggered, not scheduled: this date is a reminder, not a
            // deadline, so when the cadence itself changes it should reflect the new cadence
            // rather than a stale date computed under the old one.
            $updates['next_charge_at'] = now()->addMonths($newFrequency->intervalMonths())->toDateString();
        }

        if ($updates === []) {
            return $donation;
        }

        $donation = $this->donationRepository->update($donation, $updates);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::CUSTOMER,
            AuditActionEnum::DONATION_MODIFIED,
            $request,
            $user->uuid,
            ['donation_uuid' => $donation->uuid, ...$updates],
            $user->displayName().' modified a recurring donation to "'.$donation->campaign->title.'".',
            Donation::class,
            $donation->uuid,
            ModuleEnums::fundraising,
            200,
        );

        return $donation;
    }

    /**
     * The one place that actually confirms money moved. Called from two different entry
     * points: DonationPaymentController::verify() (customer/frontend, after the Paystack
     * redirect) and PaystackWebhookController (Paystack calling us directly); it doesn't
     * matter which gets here first, since the early-return below makes a second call for an
     * already-settled payment a harmless no-op.
     *
     * @return array<string, mixed>
     */
    public function verifyPayment(string $reference, Request $request): array
    {
        $payment = $this->paymentRepository->findByReference($reference);

        if (! $payment instanceof DonationPayment) {
            throw new ApiException('Payment not found.', 404);
        }

        // Already settled by the other entry point (webhook vs frontend verify); nothing to do.
        if ($payment->status === PaymentStatusEnum::SUCCESSFUL->value) {
            return ['payment' => $payment, 'donation' => $payment->donation];
        }

        $result = $this->paymentGatewayService->verify($reference);

        if ($result['status'] !== 'successful') {
            $payment = $this->paymentRepository->markFailed($payment);

            GeneralHelper::storeAuditLog(
                $payment->user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER,
                AuditActionEnum::PAYMENT_FAILED,
                $request,
                $payment->user?->uuid,
                ['reference' => $reference, 'gateway_status' => $result['status'], 'donor_email' => $payment->donation->donorEmail()],
                'Payment for a donation failed or was not completed.',
                DonationPayment::class,
                $payment->uuid,
                ModuleEnums::fundraising,
                200,
            );

            return ['payment' => $payment, 'donation' => $payment->donation];
        }

        return DB::transaction(function () use ($payment, $result, $reference, $request): array {
            // Captured before markSuccessful() below so it reflects the total *excluding* this
            // payment; used to detect a tier crossing (REC-04) once this payment is counted.
            $previousNgnTotal = ($payment->user !== null && $payment->currency === 'NGN')
                ? $this->paymentRepository->sumSuccessfulForUser($payment->user->id, null, null)
                : null;

            $payment = $this->paymentRepository->markSuccessful($payment, [
                'paid_at' => now(),
                'card_last_four' => $result['card_last_four'],
                'meta' => array_filter([
                    'channel' => $result['channel'],
                    'gateway_amount' => $result['amount'],
                    'gateway_currency' => $result['currency'],
                ]),
            ]);

            if ($payment->user !== null) {
                $this->paymentMethodService->saveFromAuthorization($payment->user, $result['authorization']);
            }

            if ($previousNgnTotal !== null) {
                $newNgnTotal = (string) ((float) $previousNgnTotal + (float) $payment->amount);
                $this->notifyTierUpgradeIfAny($payment->user, $previousNgnTotal, $newNgnTotal);
            }

            $donation = $payment->donation;
            $campaign = $donation->campaign;
            $frequency = DonationFrequencyEnum::from($donation->frequency);

            $donation = $this->donationRepository->incrementTotalReceived($donation, (string) $payment->amount);

            $isNowComplete = ! $frequency->isRecurring();

            $donation = $this->donationRepository->update($donation, [
                'status' => $isNowComplete ? DonationStatusEnum::COMPLETED->value : DonationStatusEnum::ACTIVE->value,
                'completed_at' => $isNowComplete ? now() : null,
                'next_charge_at' => $frequency->isRecurring() ? now()->addMonths($frequency->intervalMonths())->toDateString() : null,
            ]);

            $campaign = $this->campaignRepository->incrementRaisedAmount($campaign, (string) $payment->amount);

            $userType = $payment->user === null ? UserTypeEnum::GUEST : UserTypeEnum::CUSTOMER;

            GeneralHelper::storeAuditLog(
                $userType,
                AuditActionEnum::PAYMENT_SUCCEEDED,
                $request,
                $payment->user?->uuid,
                ['reference' => $reference, 'amount' => (string) $payment->amount, 'donor_email' => $donation->donorEmail()],
                'Payment for a donation succeeded.',
                DonationPayment::class,
                $payment->uuid,
                ModuleEnums::fundraising,
                200,
            );

            if ($isNowComplete) {
                GeneralHelper::storeAuditLog(
                    $userType,
                    AuditActionEnum::DONATION_COMPLETED,
                    $request,
                    $payment->user?->uuid,
                    ['donation_uuid' => $donation->uuid],
                    'Donation fully paid.',
                    Donation::class,
                    $donation->uuid,
                    ModuleEnums::fundraising,
                    200,
                );
            }

            $this->notifyPaymentSucceeded($donation, $campaign, $payment, $reference);

            return [
                'payment' => $payment,
                'donation' => $this->donationRepository->loadFresh($donation, ['user', 'campaign', 'payments']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->donationRepository->paginateForUser($user->id, $filters, $perPage);
    }

    public function findForUser(User $user, string $uuid): Donation
    {
        $donation = $this->donationRepository->findByUuidForUser($user->id, $uuid);

        if (! $donation instanceof Donation) {
            throw new ApiException('Donation not found.', 404);
        }

        return $donation;
    }

    /**
     * Flat, cross-donation transaction log for the "Donation History" screen: every payment
     * the donor has made, regardless of which donation it belongs to.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginatePaymentsForUser(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->paymentRepository->paginateForUser($user->id, $filters, $perPage);
    }

    public function findPaymentForUser(User $user, string $uuid): DonationPayment
    {
        $payment = $this->paymentRepository->findByUuidForUser($user->id, $uuid);

        if (! $payment instanceof DonationPayment) {
            throw new ApiException('Payment not found.', 404);
        }

        return $payment;
    }

    /**
     * "Total Donation Target" is the sum of goal_amount across every campaign this donor has
     * ever successfully paid into (not time-boxed, since a campaign's goal isn't a period-specific
     * figure); "Total Donation Received" is scoped to the given date range, if any.
     *
     * @return array{target: string, received: string}
     */
    public function historyOverview(User $user, ?string $from, ?string $to): array
    {
        $target = $this->paymentRepository->distinctCampaignGoalTotalForUser($user->id);
        $received = $this->paymentRepository->sumSuccessfulForUser($user->id, $from, $to);

        return [
            'target' => $target,
            'target_formatted' => Money::format($target, 'NGN'),
            'received' => $received,
            'received_formatted' => Money::format($received, 'NGN'),
        ];
    }

    /**
     * Kicks off payment for one donation cycle: mints our own reference, asks the gateway for a
     * checkout link (PaymentGatewayService::initialize()), and records a *pending*
     * DonationPayment row so verifyPayment() has something to find later by this reference.
     * Nothing is marked paid here; that only happens once the gateway confirms it.
     *
     * @return array{authorization_url: string, access_code: ?string, reference: string}
     */
    private function initializePaymentFor(?User $user, Donation $donation, ?string $paymentMethod, ?string $guestEmail = null): array
    {
        $reference = 'DON_'.strtoupper(Str::random(20));
        $email = $user->email ?? $guestEmail;

        $initialization = $this->paymentGatewayService->initialize(
            $reference,
            (string) $donation->amount,
            $donation->currency,
            $email,
            ['donation_uuid' => $donation->uuid],
        );

        $this->paymentRepository->create([
            'donation_id' => $donation->id,
            'user_id' => $user?->id,
            'amount' => $donation->amount,
            'currency' => $donation->currency,
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
    private function notifyPaymentSucceeded(Donation $donation, Campaign $campaign, DonationPayment $payment, string $reference): void
    {
        if ($donation->isGuest()) {
            $this->sendGuestReceipt($donation, $campaign, $payment, $reference);

            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::fundraising->value,
            event: 'payment_succeeded',
            title: 'Donation received',
            message: 'Thank you! Your donation of '.Money::format($payment->amount, $payment->currency).' towards "'.$campaign->title.'" was received.',
            meta: ['donation_uuid' => $donation->uuid, 'campaign_uuid' => $campaign->uuid],
            sendMail: true,
            mailSubject: 'Donation received - '.$campaign->title,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$donation->user->uuid], $notification);
    }

    private function sendGuestReceipt(Donation $donation, Campaign $campaign, DonationPayment $payment, string $reference): void
    {
        try {
            $theme = app(ThemeResolver::class)->resolveForMail();

            Mail::to($donation->guest_email)->send(new PaymentReceiptMail(
                $donation->guest_name,
                $theme,
                $campaign->title,
                Money::format($payment->amount, $payment->currency),
                $reference,
            ));
        } catch (\Throwable $th) {
            Log::warning('Guest donation receipt email failed.', [
                'donation_uuid' => $donation->uuid,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }
    }

    /**
     * REC-04: celebration notification when a payment pushes the donor's lifetime NGN total
     * into a new (higher) recognition tier. A no-op if they're still in the same tier (or
     * haven't reached the lowest one), or if $previousTotal is null (guest, or non-NGN
     * payment, out of the NGN-only Recognition Wall's scope; see RecognitionService).
     */
    private function notifyTierUpgradeIfAny(?User $user, string $previousTotal, string $newTotal): void
    {
        if ($user === null) {
            return;
        }

        $previousTier = $this->donorTierRepository->findForAmount($previousTotal);
        $newTier = $this->donorTierRepository->findForAmount($newTotal);

        if ($newTier === null || $previousTier?->id === $newTier->id) {
            return;
        }

        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::fundraising->value,
            event: 'donor_tier_reached',
            title: 'New recognition tier reached',
            message: 'Congratulations! Your generosity has earned you the "'.$newTier->name.'" tier on our Recognition Wall.',
            meta: ['tier' => $newTier->name],
            sendMail: true,
            mailSubject: 'You\'ve reached '.$newTier->name.'!',
        );

        $this->notificationDispatchService->notifyUsersByUuids([$user->uuid], $notification);
    }
}
