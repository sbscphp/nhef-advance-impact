<?php

namespace App\Services\ConstituentManagement;

use App\Enums\AuditActionEnum;
use App\Enums\ConstituentStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Jobs\SendConstituentInviteEmailJob;
use App\Models\Admin;
use App\Models\DonationPayment;
use App\Models\Pledge;
use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\Contracts\Donation\DonationPaymentRepositoryInterface;
use App\Repositories\Contracts\Donation\DonationRepositoryInterface;
use App\Repositories\Contracts\DonorTier\DonorTierRepositoryInterface;
use App\Repositories\Contracts\Pledge\PledgeRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use App\Services\Notifications\NotificationDispatchService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdminConstituentService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly DonationRepositoryInterface $donationRepository,
        private readonly DonationPaymentRepositoryInterface $paymentRepository,
        private readonly PledgeRepositoryInterface $pledgeRepository,
        private readonly DonorTierRepositoryInterface $donorTierRepository,
        private readonly NotificationDispatchService $notificationDispatchService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function invite(array $payload, Admin $actor, Request $request): User
    {
        if ($this->userRepository->emailExists((string) $payload['email'])) {
            throw new ApiException('A constituent with this email already exists.', 422);
        }

        $user = $this->userRepository->create([
            'firstname' => $payload['first_name'],
            'lastname' => $payload['last_name'],
            'email' => $payload['email'],
            'phone_number' => $payload['phone_number'] ?? null,
            'invite_message' => $payload['invite_message'] ?? null,
            // Random placeholder; the constituent sets a real password via the emailed invite link.
            'password' => bin2hex(random_bytes(16)),
            'email_verified_at' => now(),
            'status' => ConstituentStatusEnum::INVITE_SENT->value,
            'is_active' => true,
            'can_login' => true,
            'created_by' => $actor->uuid,
            'invited_at' => now(),
        ]);

        // TODO: switch back to ::dispatch() (queued) once a worker is confirmed running in QA;
        // dispatchSync runs it inline so invite emails go out immediately without one.
        // SendConstituentInviteEmailJob::dispatch($user->uuid);
        SendConstituentInviteEmailJob::dispatchSync($user->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_INVITED,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName(), 'email' => $user->email],
            $actor->displayName().' invited a constituent: '.$user->displayName().'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            201,
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->userRepository->paginateForAdmin($filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, User>, 1: bool}
     */
    public function exportForAdmin(array $filters): array
    {
        return $this->userRepository->exportForAdmin($filters);
    }

    /**
     * @return array{all: int, active: int, access_revoked: int}
     */
    public function overview(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        return $this->userRepository->countByStatus($start, $end);
    }

    public function findForAdmin(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user instanceof User) {
            throw new ApiException('Constituent not found.', 404);
        }

        return $user;
    }

    /**
     * Same as {@see self::findForAdmin()} but attaches the donor tier as a transient, non-column
     * attribute for ConstituentDetailResource to read; never call this on a User that will be
     * saved afterwards.
     */
    public function showForAdmin(string $uuid): User
    {
        $user = $this->findForAdmin($uuid);
        $user->setAttribute('tier', $this->resolveTierLabel($user));

        return $user;
    }

    public function resolveTierLabel(User $user): ?string
    {
        $total = $this->paymentRepository->sumSuccessfulForUser($user->id, null, null);
        $tier = $this->donorTierRepository->findForAmount($total);

        return $tier?->name;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): User
    {
        $user = $this->findForAdmin($uuid);

        $updates = [];
        if (array_key_exists('first_name', $payload)) {
            $updates['firstname'] = $payload['first_name'];
        }
        if (array_key_exists('last_name', $payload)) {
            $updates['lastname'] = $payload['last_name'];
        }
        foreach (['email', 'phone_number', 'invite_message', 'status'] as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[$field] = $payload[$field];
            }
        }

        if (isset($updates['status'])) {
            $updates['is_active'] = $updates['status'] !== ConstituentStatusEnum::ACCESS_REVOKED->value;
            $updates['can_login'] = $updates['is_active'];
        }

        if ($updates !== []) {
            $user = $this->userRepository->update($user, $updates);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_UPDATED,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName()],
            $actor->displayName().' updated a constituent: '.$user->displayName().'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $user;
    }

    public function revokeAccess(string $uuid, Admin $actor, Request $request): User
    {
        $user = $this->findForAdmin($uuid);

        if ($user->status === ConstituentStatusEnum::ACCESS_REVOKED->value) {
            throw new ApiException('Constituent access is already revoked.', 422);
        }

        $user = $this->userRepository->update($user, [
            'status' => ConstituentStatusEnum::ACCESS_REVOKED->value,
            'is_active' => false,
            'can_login' => false,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_ACCESS_REVOKED,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName()],
            $actor->displayName().' revoked access for constituent: '.$user->displayName().'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $user;
    }

    public function reactivateAccess(string $uuid, Admin $actor, Request $request): User
    {
        $user = $this->findForAdmin($uuid);

        if ($user->status !== ConstituentStatusEnum::ACCESS_REVOKED->value) {
            throw new ApiException('Only a constituent with revoked access can be reactivated.', 422);
        }

        $user = $this->userRepository->update($user, [
            'status' => ConstituentStatusEnum::ACTIVE->value,
            'is_active' => true,
            'can_login' => true,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_REACTIVATED,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName()],
            $actor->displayName().' reactivated access for constituent: '.$user->displayName().'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $user;
    }

    public function resendInvite(string $uuid, Admin $actor, Request $request): User
    {
        $user = $this->findForAdmin($uuid);

        if ($user->status !== ConstituentStatusEnum::INVITE_SENT->value) {
            throw new ApiException('Invite can only be resent while the constituent is pending onboarding.', 422);
        }

        $user = $this->userRepository->update($user, ['invited_at' => now()]);

        // TODO: switch back to ::dispatch() (queued) once a worker is confirmed running in QA;
        // dispatchSync runs it inline so invite emails go out immediately without one.
        // SendConstituentInviteEmailJob::dispatch($user->uuid);
        SendConstituentInviteEmailJob::dispatchSync($user->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_INVITE_RESENT,
            $request,
            $actor->uuid,
            ['user_uuid' => $user->uuid, 'name' => $user->displayName()],
            $actor->displayName().' resent the onboarding invite for constituent: '.$user->displayName().'.',
            User::class,
            $user->uuid,
            ModuleEnums::constituent_management,
            200,
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateDonations(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->donationRepository->paginateForUser($user->id, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginatePayments(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->paymentRepository->paginateForUser($user->id, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, DonationPayment>, 1: bool}
     */
    public function exportPayments(User $user, array $filters): array
    {
        return $this->paymentRepository->exportForUser($user->id, $filters);
    }

    public function findPaymentForAdmin(User $user, string $uuid): DonationPayment
    {
        $payment = $this->paymentRepository->findByUuidForUser($user->id, $uuid);

        if (! $payment instanceof DonationPayment) {
            throw new ApiException('Donation payment not found.', 404);
        }

        return $payment;
    }

    /**
     * @return array{target: string, target_formatted: string, received: string, received_formatted: string}
     */
    public function paymentsOverview(User $user, ?string $from, ?string $to): array
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
     * @param  array<string, mixed>  $filters
     */
    public function paginatePledges(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->pledgeRepository->paginateForUser($user->id, $filters, $perPage);
    }

    public function findPledgeForAdmin(User $user, string $uuid): Pledge
    {
        $pledge = $this->pledgeRepository->findByUuidForUser($user->id, $uuid);

        if (! $pledge instanceof Pledge) {
            throw new ApiException('Pledge not found.', 404);
        }

        return $pledge;
    }

    /**
     * @return array{count: int, total_pledged: string, total_pledged_formatted: string, total_fulfilled: string, total_fulfilled_formatted: string}
     */
    public function pledgesOverview(User $user): array
    {
        $overview = $this->pledgeRepository->overviewForUser($user->id);

        return [
            'count' => $overview['count'],
            'total_pledged' => $overview['total_pledged'],
            'total_pledged_formatted' => Money::format($overview['total_pledged'], 'NGN'),
            'total_fulfilled' => $overview['total_fulfilled'],
            'total_fulfilled_formatted' => Money::format($overview['total_fulfilled'], 'NGN'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendPledgeReminder(Pledge $pledge, User $user, array $payload, Admin $actor, Request $request): void
    {
        $notification = new GenericDatabaseNotification(
            module: ModuleEnums::fundraising->value,
            event: 'pledge_reminder',
            title: $payload['title'],
            message: $payload['message'],
            meta: ['pledge_uuid' => $pledge->uuid],
            sendMail: true,
        );

        $this->notificationDispatchService->notifyUsersByUuids([$user->uuid], $notification);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::CONSTITUENT_PLEDGE_REMINDER_SENT,
            $request,
            $actor->uuid,
            ['pledge_uuid' => $pledge->uuid, 'user_uuid' => $user->uuid],
            $actor->displayName().' sent a pledge reminder to '.$user->displayName().'.',
            Pledge::class,
            $pledge->uuid,
            ModuleEnums::constituent_management,
            200,
        );
    }
}
