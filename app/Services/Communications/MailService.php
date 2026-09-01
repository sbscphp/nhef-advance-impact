<?php

namespace App\Services\Communications;

use App\Enums\AuditActionEnum;
use App\Enums\MailRecipientStatusEnum;
use App\Enums\MailStatusEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Exceptions\ApiException;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Http\Resources\Communications\EmailUnsubscribeResource;
use App\Jobs\SendMailCampaignJob;
use App\Models\Admin;
use App\Models\EmailUnsubscribe;
use App\Models\Mail as MailCampaign;
use App\Models\MailRecipient;
use App\Repositories\Contracts\Communications\EmailUnsubscribeRepositoryInterface;
use App\Repositories\Contracts\Communications\MailRecipientRepositoryInterface;
use App\Repositories\Contracts\Communications\MailRepositoryInterface;
use App\Repositories\Contracts\User\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MailService
{
    private const MAX_EXPORT_ROWS = 5000;

    public function __construct(
        private readonly MailRepositoryInterface $mailRepository,
        private readonly MailRecipientRepositoryInterface $recipientRepository,
        private readonly EmailUnsubscribeRepositoryInterface $unsubscribeRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(array $payload, Admin $actor, Request $request): MailCampaign
    {
        $bannerUrl = FileUploadHelper::smartSingleFileUpload($payload['banner'] ?? null, 'communications/mail-banners');

        $mail = $this->mailRepository->create([
            'title' => $payload['title'],
            'banner_url' => $bannerUrl,
            'body' => $payload['body'],
            'send_at' => $payload['send_at'] ?? null,
            'segment_criteria' => $this->buildAudienceCriteria($payload),
            'status' => MailStatusEnum::DRAFT->value,
            'created_by' => $actor->uuid,
        ]);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MAIL_CREATED,
            $request,
            $actor->uuid,
            ['mail_uuid' => $mail->uuid],
            $actor->displayName().' drafted a mail: '.$mail->title.'.',
            MailCampaign::class,
            $mail->uuid,
            ModuleEnums::communications,
            201,
        );

        return $this->attachPickedRecipients($mail);
    }

    public function find(string $uuid): MailCampaign
    {
        $mail = $this->mailRepository->findByUuid($uuid);

        if (! $mail instanceof MailCampaign) {
            throw new ApiException('Mail not found.', 404);
        }

        return $this->attachPickedRecipients($mail);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
        $paginator = $this->mailRepository->paginate($filters, $perPage);

        $this->attachPickedRecipientsToMany(collect($paginator->items()));

        return $paginator;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, MailCampaign>, 1: bool}
     */
    public function exportCollection(array $filters): array
    {
        return $this->mailRepository->exportCollection($filters, self::MAX_EXPORT_ROWS);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, EmailUnsubscribe>, 1: bool}
     */
    public function exportUnsubscribersCollection(array $filters): array
    {
        return $this->unsubscribeRepository->exportCollection($filters, self::MAX_EXPORT_ROWS);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, MailRecipient>, 1: bool}
     */
    public function exportRecipientsCollection(string $uuid, array $filters): array
    {
        $mail = $this->find($uuid);

        return $this->recipientRepository->exportCollectionForMail($mail->id, $filters, self::MAX_EXPORT_ROWS);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $uuid, array $payload, Admin $actor, Request $request): MailCampaign
    {
        $mail = $this->find($uuid);
        $this->assertIsDraft($mail);

        $bannerUrl = array_key_exists('banner', $payload)
            ? FileUploadHelper::smartSingleFileUpload($payload['banner'], 'communications/mail-banners')
            : $mail->banner_url;

        $data = array_filter([
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
        ], fn ($value) => $value !== null);

        $data['banner_url'] = $bannerUrl;

        if (array_key_exists('send_at', $payload)) {
            $data['send_at'] = $payload['send_at'];
        }

        if (array_key_exists('segment', $payload) || array_key_exists('recipient_user_ids', $payload)) {
            $data['segment_criteria'] = $this->buildAudienceCriteria($payload, $mail->segment_criteria ?? []);
        }

        $mail = $this->mailRepository->update($mail, $data);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MAIL_UPDATED,
            $request,
            $actor->uuid,
            ['mail_uuid' => $mail->uuid],
            $actor->displayName().' updated mail: '.$mail->title.'.',
            MailCampaign::class,
            $mail->uuid,
            ModuleEnums::communications,
            200,
        );

        return $this->attachPickedRecipients($mail);
    }

    public function delete(string $uuid, Admin $actor, Request $request): void
    {
        $mail = $this->find($uuid);
        $this->assertIsDraft($mail);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MAIL_DELETED,
            $request,
            $actor->uuid,
            ['mail_uuid' => $mail->uuid],
            $actor->displayName().' deleted mail: '.$mail->title.'.',
            MailCampaign::class,
            $mail->uuid,
            ModuleEnums::communications,
            200,
        );

        $this->mailRepository->delete($mail);
    }

    /**
     * Resolves the audience fresh from `segment_criteria`, writes `mail_recipients`, and queues
     * delivery. Pass `send_at` in the future to schedule instead of sending immediately.
     *
     * @param  array<string, mixed>  $payload
     */
    public function send(string $uuid, array $payload, Admin $actor, Request $request): MailCampaign
    {
        $mail = $this->find($uuid);
        $this->assertSendable($mail);

        $recipients = $this->resolveRecipients($mail);

        if ($recipients->isEmpty()) {
            throw new ApiException('No recipients match this mail\'s audience.', 422);
        }

        $this->recipientRepository->replaceForMail($mail->id, $recipients->all());

        $sendAt = match (true) {
            filled($payload['send_at'] ?? null) => Carbon::parse($payload['send_at']),
            filled($mail->send_at) => Carbon::parse($mail->send_at),
            default => null,
        };
        $isScheduled = $sendAt instanceof Carbon && $sendAt->isFuture();

        $mail = $this->mailRepository->update($mail, [
            'send_at' => $sendAt,
            'sent_by' => $actor->uuid,
            'status' => $isScheduled ? MailStatusEnum::SCHEDULED->value : MailStatusEnum::SENDING->value,
        ]);

        if ($isScheduled) {
            SendMailCampaignJob::dispatch($mail->uuid)->delay($sendAt);
        } else {
            SendMailCampaignJob::dispatch($mail->uuid);
        }

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MAIL_SENT,
            $request,
            $actor->uuid,
            ['mail_uuid' => $mail->uuid, 'recipient_count' => $recipients->count(), 'send_at' => $sendAt?->toIso8601String()],
            $actor->displayName().($isScheduled ? ' scheduled' : ' sent').' mail: '.$mail->title.'.',
            MailCampaign::class,
            $mail->uuid,
            ModuleEnums::communications,
            200,
        );

        return $this->find($mail->uuid);
    }

    /** Only retries recipients still `pending`/`failed`; anyone already `sent` is left alone. */
    public function resend(string $uuid, Admin $actor, Request $request): MailCampaign
    {
        $mail = $this->find($uuid);

        $recipients = $this->recipientRepository->listForMail($mail->id);

        if ($recipients->isEmpty()) {
            throw new ApiException('This mail has never been sent.', 422);
        }

        $outstanding = $recipients->whereIn('status', [
            MailRecipientStatusEnum::PENDING->value,
            MailRecipientStatusEnum::FAILED->value,
        ]);

        if ($outstanding->isEmpty()) {
            throw new ApiException('Every recipient has already received this mail.', 422);
        }

        SendMailCampaignJob::dispatch($mail->uuid);

        GeneralHelper::storeAuditLog(
            UserTypeEnum::ADMIN,
            AuditActionEnum::MAIL_RESENT,
            $request,
            $actor->uuid,
            ['mail_uuid' => $mail->uuid, 'outstanding_count' => $outstanding->count()],
            $actor->displayName().' re-sent mail: '.$mail->title.'.',
            MailCampaign::class,
            $mail->uuid,
            ModuleEnums::communications,
            200,
        );

        return $mail;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateRecipients(string $uuid, array $filters): LengthAwarePaginator
    {
        $mail = $this->find($uuid);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->recipientRepository->paginateForMail($mail->id, $filters, $perPage);
    }

    /**
     * @return array{recipient_count: int, sent: int, opened: int, failed: int, unsubscribed: int, open_rate: float, university_breakdown: array<string, int>}
     */
    public function analytics(string $uuid): array
    {
        $mail = $this->find($uuid);
        $recipients = $this->recipientRepository->listForMail($mail->id);

        $sent = $recipients->where('status', MailRecipientStatusEnum::SENT->value)->count();
        $opened = $recipients->whereNotNull('opened_at')->count();
        $failed = $recipients->where('status', MailRecipientStatusEnum::FAILED->value)->count();

        return [
            'recipient_count' => $recipients->count(),
            'sent' => $sent,
            'opened' => $opened,
            'failed' => $failed,
            'unsubscribed' => $this->recipientRepository->unsubscribedCountForMail($mail->id),
            'open_rate' => $sent > 0 ? round(($opened / $sent) * 100, 1) : 0.0,
            'university_breakdown' => $this->recipientRepository->universityBreakdown($mail->id)->all(),
        ];
    }

    /**
     * Overview stats (scoped to the given period, all-time if none given) plus a rolling
     * `trend_days`-day send/open trend, for the Mails dashboard.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     total_mails: int, total_reach: int, open_rate: float, unsubscribe_rate: float,
     *     by_status: array<string, int>,
     *     trend: array{send: array<string, int>, open: array<string, int>},
     *     recent_unsubscribers: array<int, array<string, mixed>>,
     * }
     */
    public function dashboard(array $filters = []): array
    {
        $window = ListingFilterRules::resolveDateWindow($filters);
        $trendDays = max(1, min((int) ($filters['trend_days'] ?? 7), 90));

        $stats = $this->recipientRepository->statsInRange($window['start'], $window['end']);
        $openRate = $stats['total_sent'] > 0 ? round(($stats['total_opened'] / $stats['total_sent']) * 100, 1) : 0.0;
        $unsubscribeRate = $stats['total_sent'] > 0 ? round(($stats['total_unsubscribed'] / $stats['total_sent']) * 100, 1) : 0.0;

        return [
            'total_mails' => $this->mailRepository->countInRange($window['start'], $window['end']),
            'total_reach' => $stats['total_reach'],
            'open_rate' => $openRate,
            'unsubscribe_rate' => $unsubscribeRate,
            'by_status' => $this->mailRepository->countByStatus(),
            'trend' => [
                'send' => $this->recipientRepository->sendTrend($trendDays)->all(),
                'open' => $this->recipientRepository->openTrend($trendDays)->all(),
            ],
            'recent_unsubscribers' => EmailUnsubscribeResource::collection(
                $this->unsubscribeRepository->recent(5)
            )->resolve(),
        ];
    }

    /** Marks one recipient's open pixel hit; only the first hit sets `opened_at`, every hit increments `open_count`. */
    public function trackOpen(string $recipientUuid): void
    {
        $recipient = $this->recipientRepository->findByUuid($recipientUuid);

        if ($recipient === null) {
            return;
        }

        $this->recipientRepository->update($recipient, [
            'opened_at' => $recipient->opened_at ?? now(),
            'open_count' => $recipient->open_count + 1,
        ]);
    }

    public function unsubscribe(string $recipientUuid): void
    {
        $recipient = $this->recipientRepository->findByUuid($recipientUuid);

        if ($recipient === null) {
            return;
        }

        $this->unsubscribeRepository->create([
            'email' => $recipient->email,
            'user_id' => $recipient->user_id,
            'mail_id' => $recipient->mail_id,
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateUnsubscribers(array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        return $this->unsubscribeRepository->paginate($filters, $perPage);
    }

    /**
     * Builds the union of segment matches and individually-picked recipients, deduped by email,
     * excluding anyone on the global unsubscribe list.
     *
     * @return Collection<int, array{user_id: int|null, email: string}>
     */
    private function resolveRecipients(MailCampaign $mail): Collection
    {
        $criteria = $mail->segment_criteria ?? [];
        $segmentMembers = $this->userRepository->resolveSegmentMembers($criteria);
        $pickedUuids = $criteria['recipient_user_ids'] ?? [];
        $pickedMembers = $pickedUuids !== [] ? $this->userRepository->findManyByUuids($pickedUuids) : collect();

        $merged = $segmentMembers->concat($pickedMembers)->unique('email');

        $emails = $merged->pluck('email')->all();
        $unsubscribed = $emails !== [] ? $this->unsubscribeRepository->unsubscribedEmails($emails) : [];

        return $merged
            ->reject(fn ($user) => in_array($user->email, $unsubscribed, true))
            ->map(fn ($user) => ['user_id' => $user->id, 'email' => $user->email])
            ->values();
    }

    /** Resolves picked UUIDs into User records as a `pickedRecipients` pseudo-relation, so MailResource can show names/emails instead of bare UUIDs. */
    private function attachPickedRecipients(MailCampaign $mail): MailCampaign
    {
        $uuids = $mail->segment_criteria['recipient_user_ids'] ?? [];
        $mail->setRelation('pickedRecipients', $uuids !== [] ? $this->userRepository->findManyByUuids($uuids) : collect());

        return $mail;
    }

    /**
     * Batched version of attachPickedRecipients() for a paginated/listed set of mails: one query
     * for every picked UUID across the whole set, instead of one query per mail.
     *
     * @param  Collection<int, MailCampaign>  $mails
     */
    private function attachPickedRecipientsToMany(Collection $mails): void
    {
        $allUuids = $mails
            ->flatMap(fn (MailCampaign $mail) => $mail->segment_criteria['recipient_user_ids'] ?? [])
            ->unique()
            ->values()
            ->all();

        $users = $allUuids !== [] ? $this->userRepository->findManyByUuids($allUuids)->keyBy('uuid') : collect();

        $mails->each(function (MailCampaign $mail) use ($users): void {
            $uuids = $mail->segment_criteria['recipient_user_ids'] ?? [];
            $mail->setRelation('pickedRecipients', collect($uuids)->map(fn ($uuid) => $users->get($uuid))->filter()->values());
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function buildAudienceCriteria(array $payload, array $existing = []): array
    {
        $segment = $payload['segment'] ?? [];

        return array_filter([
            'university' => $segment['university'] ?? $existing['university'] ?? null,
            'department' => $segment['department'] ?? $existing['department'] ?? null,
            'graduation_year_from' => $segment['graduation_year_from'] ?? $existing['graduation_year_from'] ?? null,
            'graduation_year_to' => $segment['graduation_year_to'] ?? $existing['graduation_year_to'] ?? null,
            'recipient_user_ids' => $payload['recipient_user_ids'] ?? $existing['recipient_user_ids'] ?? [],
        ], fn ($value) => $value !== null && $value !== []);
    }

    private function assertIsDraft(MailCampaign $mail): void
    {
        if ($mail->status !== MailStatusEnum::DRAFT->value) {
            throw new ApiException('Only a draft mail can be edited or deleted.', 422);
        }
    }

    private function assertSendable(MailCampaign $mail): void
    {
        if (in_array($mail->status, [MailStatusEnum::SENDING->value, MailStatusEnum::SENT->value], true)) {
            throw new ApiException('This mail has already been sent.', 422);
        }
    }
}
