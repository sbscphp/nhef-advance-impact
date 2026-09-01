<?php

namespace App\Services\Communications;

use App\Enums\AuditActionEnum;
use App\Enums\CommunicationCallPriorityEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\CommunicationCallLog;
use App\Models\User;
use App\Repositories\Contracts\Communications\CommunicationCallLogRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CallLogService
{
    public function __construct(
        private readonly CommunicationCallLogRepositoryInterface $callLogRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TaskService $taskService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): CommunicationCallLog
    {
        $contact = $this->resolveContact($payload['contact_user_uuid']);

        $call = $this->callLogRepository->create([
            'contact_user_id' => $contact->id,
            'logged_by' => $actor->uuid,
            'purpose' => $payload['purpose'],
            'description' => $payload['description'],
            'priority' => $payload['priority'] ?? CommunicationCallPriorityEnum::MEDIUM->value,
            'call_date' => now(),
        ]);

        $call->setRelation('contact', $contact);
        $call->setRelation('logger', $actor);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::COMMUNICATION_CALL_LOGGED,
            $request,
            $actor->uuid,
            ['call_uuid' => $call->uuid, 'contact_uuid' => $contact->uuid],
            $actor->displayName().' logged a call with '.$contact->displayName().'.',
            CommunicationCallLog::class,
            $call->uuid,
            ModuleEnums::communications,
            201,
        );

        foreach ($payload['follow_up_tasks'] ?? [] as $followUpTask) {
            $this->taskService->create([...$followUpTask, 'call_log_id' => $call->id], $actor, $request);
        }

        return $this->find($call->uuid);
    }

    /**
     * Adds one more follow-up task to an already-logged call (the "+ New Task" action on the
     * call log detail screen), distinct from the follow-up tasks optionally added at log time.
     *
     * @param  array<string, mixed>  $payload
     */
    public function addTask(string $callUuid, array $payload, Admin $actor, Request $request): CommunicationCallLog
    {
        $call = $this->find($callUuid);

        $this->taskService->create([...$payload, 'call_log_id' => $call->id], $actor, $request);

        return $this->find($call->uuid);
    }

    public function find(string $uuid): CommunicationCallLog
    {
        $call = $this->callLogRepository->findByUuid($uuid);

        if (! $call instanceof CommunicationCallLog) {
            throw new ApiException('Call log not found.', 404);
        }

        return $call;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->callLogRepository->paginate($filters, $perPage);
    }

    /**
     * Counts distinct call logs with a follow-up task in each state, not raw task rows, so these never exceed `total`.
     *
     * @return array{total: int, upcoming: int, due_today: int, overdue: int}
     */
    public function overview(): array
    {
        return $this->callLogRepository->overviewStats();
    }

    private function resolveContact(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user instanceof User) {
            throw new ApiException('The selected contact does not exist.', 422);
        }

        return $user;
    }
}
