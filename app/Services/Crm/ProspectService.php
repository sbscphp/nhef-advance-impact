<?php

namespace App\Services\Crm;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\ProspectCallPriorityEnum;
use App\Enums\ProspectInviteTypeEnum;
use App\Enums\ProspectMessageStatusEnum;
use App\Enums\ProspectPipelineStageEnum;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Jobs\SendProspectMessageJob;
use App\Models\Admin;
use App\Models\Prospect;
use App\Models\ProspectCallLog;
use App\Models\ProspectInvite;
use App\Models\ProspectMessage;
use App\Repositories\Contracts\Admin\AdminRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectCallLogRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectInviteRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectMessageRepositoryInterface;
use App\Repositories\Contracts\Crm\ProspectRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProspectService
{
    public function __construct(
        private readonly ProspectRepositoryInterface $prospectRepository,
        private readonly ProspectCallLogRepositoryInterface $callLogRepository,
        private readonly ProspectInviteRepositoryInterface $inviteRepository,
        private readonly ProspectMessageRepositoryInterface $messageRepository,
        private readonly AdminRepositoryInterface $adminRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): Prospect
    {
        $assignedAdmin = $this->resolveAdmin((string) $payload['assign_to']);

        $prospect = $this->prospectRepository->create([
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'] ?? null,
            'lead_source' => $payload['lead_source'],
            'estimated_value' => $payload['estimated_value'],
            'currency' => $payload['currency'] ?? 'NGN',
            'stage' => ProspectPipelineStageEnum::IDENTIFICATION->value,
            'stage_entered_at' => now(),
            'assigned_admin_id' => $assignedAdmin->id,
            'created_by' => $actor->uuid,
        ]);

        $prospect->setRelation('assignedAdmin', $assignedAdmin);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_CREATED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid, 'name' => $prospect->fullName()],
            $actor->displayName().' added a new prospect: '.$prospect->fullName().'.',
            Prospect::class,
            $prospect->uuid,
            ModuleEnums::crm,
            201,
        );

        return $prospect;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): Prospect
    {
        $prospect = $this->findForAdmin($uuid);

        $data = array_filter([
            'first_name' => $payload['first_name'] ?? null,
            'last_name' => $payload['last_name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'lead_source' => $payload['lead_source'] ?? null,
            'estimated_value' => $payload['estimated_value'] ?? null,
        ], fn ($value) => $value !== null);

        if (array_key_exists('assign_to', $payload) && $payload['assign_to'] !== null) {
            $data['assigned_admin_id'] = $this->resolveAdmin((string) $payload['assign_to'])->id;
        }

        $prospect = $this->prospectRepository->update($prospect, $data);
        $prospect = $this->prospectRepository->findByUuid($prospect->uuid) ?? $prospect;

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_UPDATED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid],
            $actor->displayName().' updated prospect: '.$prospect->fullName().'.',
            Prospect::class,
            $prospect->uuid,
            ModuleEnums::crm,
            200,
        );

        return $prospect;
    }

    public function findForAdmin(string $uuid): Prospect
    {
        $prospect = $this->prospectRepository->findByUuid($uuid);

        if (! $prospect instanceof Prospect) {
            throw new ApiException('Prospect not found.', 404);
        }

        return $prospect;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForAdmin(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->prospectRepository->paginate($filters, $perPage);
    }

    /**
     * @return Collection<string, Collection<int, Prospect>>
     */
    public function kanban(): Collection
    {
        return $this->prospectRepository->kanban();
    }

    public function changeStage(string $uuid, string $stage, Admin $actor, Request $request): Prospect
    {
        $prospect = $this->findForAdmin($uuid);

        if (! in_array($stage, ProspectPipelineStageEnum::values(), true)) {
            throw new ApiException('Invalid pipeline stage.', 422);
        }

        $fromStage = $prospect->stage;

        $prospect = $this->prospectRepository->update($prospect, [
            'stage' => $stage,
            'stage_entered_at' => now(),
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_STAGE_CHANGED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid, 'from' => $fromStage, 'to' => $stage],
            $actor->displayName().' moved '.$prospect->fullName().' from '.$fromStage.' to '.$stage.'.',
            Prospect::class,
            $prospect->uuid,
            ModuleEnums::crm,
            200,
        );

        return $prospect;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logCall(string $uuid, array $payload, Admin $actor, Request $request): ProspectCallLog
    {
        $prospect = $this->findForAdmin($uuid);

        $call = $this->callLogRepository->create([
            'prospect_id' => $prospect->id,
            'logged_by' => $actor->uuid,
            'purpose' => $payload['call_purpose'],
            'description' => $payload['description'],
            'priority' => $payload['priority'] ?? ProspectCallPriorityEnum::MEDIUM->value,
            'call_date' => now(),
        ]);

        $call->setRelation('logger', $actor);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_CALL_LOGGED,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid, 'call_uuid' => $call->uuid],
            $actor->displayName().' logged a call with '.$prospect->fullName().'.',
            ProspectCallLog::class,
            $call->uuid,
            ModuleEnums::crm,
            201,
        );

        return $call;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateCalls(string $uuid, array $filters): LengthAwarePaginator
    {
        $prospect = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->callLogRepository->paginateForProspect($prospect->id, $filters, $perPage);
    }

    public function findCall(string $uuid, string $callUuid): ProspectCallLog
    {
        $prospect = $this->findForAdmin($uuid);
        $call = $this->callLogRepository->findByUuidForProspect($prospect->id, $callUuid);

        if (! $call instanceof ProspectCallLog) {
            throw new ApiException('Call log not found.', 404);
        }

        return $call;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendInvite(string $uuid, array $payload, Admin $actor, Request $request): ProspectInvite
    {
        $prospect = $this->findForAdmin($uuid);
        $inviteType = $payload['invite_type'];

        $invite = $this->inviteRepository->create([
            'prospect_id' => $prospect->id,
            'sent_by' => $actor->uuid,
            'title' => $payload['title'],
            'description' => $payload['description'],
            'starts_at' => $payload['start_date'].' '.$payload['time'],
            'ends_at' => $payload['end_date'].' '.$payload['time'],
            'invite_type' => $inviteType,
            'virtual_link' => $inviteType === ProspectInviteTypeEnum::ONLINE->value ? ($payload['virtual_link'] ?? null) : null,
            'venue' => $inviteType === ProspectInviteTypeEnum::PHYSICAL->value ? ($payload['venue'] ?? null) : null,
        ]);

        $invite->setRelation('sender', $actor);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_INVITE_SENT,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid, 'invite_uuid' => $invite->uuid],
            $actor->displayName().' sent an invite to '.$prospect->fullName().': '.$invite->title.'.',
            ProspectInvite::class,
            $invite->uuid,
            ModuleEnums::crm,
            201,
        );

        return $invite;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateInvites(string $uuid, array $filters): LengthAwarePaginator
    {
        $prospect = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->inviteRepository->paginateForProspect($prospect->id, $filters, $perPage);
    }

    public function findInvite(string $uuid, string $inviteUuid): ProspectInvite
    {
        $prospect = $this->findForAdmin($uuid);
        $invite = $this->inviteRepository->findByUuidForProspect($prospect->id, $inviteUuid);

        if (! $invite instanceof ProspectInvite) {
            throw new ApiException('Invite not found.', 404);
        }

        return $invite;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function composeMessage(string $uuid, array $payload, Admin $actor, Request $request): ProspectMessage
    {
        $prospect = $this->findForAdmin($uuid);

        $bannerUrl = FileUploadHelper::smartSingleFileUpload($payload['banner'] ?? null, 'crm/message-banners');
        $sendAt = Carbon::parse($payload['send_date']);
        $isImmediate = $sendAt->lessThanOrEqualTo(now());

        $message = $this->messageRepository->create([
            'prospect_id' => $prospect->id,
            'sent_by' => $actor->uuid,
            'subject' => $payload['subject'],
            'body' => $payload['body'],
            'banner_url' => $bannerUrl,
            'send_at' => $sendAt,
            'status' => ProspectMessageStatusEnum::SCHEDULED->value,
        ]);

        $message->setRelation('sender', $actor);

        // Always queued, never sent inline; status only flips once a worker runs the job.
        if ($isImmediate) {
            SendProspectMessageJob::dispatch($message->uuid);
        } else {
            SendProspectMessageJob::dispatch($message->uuid)->delay($sendAt);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::PROSPECT_MESSAGE_SENT,
            $request,
            $actor->uuid,
            ['prospect_uuid' => $prospect->uuid, 'message_uuid' => $message->uuid, 'status' => $message->status],
            $actor->displayName().' sent a message to '.$prospect->fullName().': '.$message->subject.'.',
            ProspectMessage::class,
            $message->uuid,
            ModuleEnums::communications,
            201,
        );

        return $message;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateMessages(string $uuid, array $filters): LengthAwarePaginator
    {
        $prospect = $this->findForAdmin($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->messageRepository->paginateForProspect($prospect->id, $filters, $perPage);
    }

    public function findMessage(string $uuid, string $messageUuid): ProspectMessage
    {
        $prospect = $this->findForAdmin($uuid);
        $message = $this->messageRepository->findByUuidForProspect($prospect->id, $messageUuid);

        if (! $message instanceof ProspectMessage) {
            throw new ApiException('Message not found.', 404);
        }

        return $message;
    }

    private function resolveAdmin(string $uuid): Admin
    {
        $admin = $this->adminRepository->findByUuid($uuid);

        if (! $admin instanceof Admin) {
            throw new ApiException('The selected assignee does not exist.', 422);
        }

        return $admin;
    }
}
