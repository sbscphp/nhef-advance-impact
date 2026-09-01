<?php

namespace App\Repositories\Communications;

use App\Enums\MailRecipientStatusEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use App\Models\EmailUnsubscribe;
use App\Models\MailRecipient;
use App\Repositories\Contracts\Communications\MailRecipientRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MailRecipientRepository implements MailRecipientRepositoryInterface
{
    public function replaceForMail(int $mailId, array $recipients): Collection
    {
        MailRecipient::query()->where('mail_id', $mailId)->delete();

        return collect($recipients)->map(fn (array $recipient) => MailRecipient::create([
            'mail_id' => $mailId,
            'user_id' => $recipient['user_id'],
            'email' => $recipient['email'],
            'status' => MailRecipientStatusEnum::PENDING->value,
        ]));
    }

    public function listForMail(int $mailId): Collection
    {
        return MailRecipient::query()->where('mail_id', $mailId)->orderBy('id')->get();
    }

    public function findByUuid(string $uuid): ?MailRecipient
    {
        return MailRecipient::query()->where('uuid', $uuid)->first();
    }

    public function update(MailRecipient $recipient, array $data): MailRecipient
    {
        $recipient->forceFill($data)->save();

        return $recipient;
    }

    public function paginateForMail(int $mailId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQueryForMail($mailId, $filters)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, MailRecipient>, 1: bool}
     */
    public function exportCollectionForMail(int $mailId, array $filters, int $maxRows): array
    {
        $query = $this->filteredQueryForMail($mailId, $filters);
        $total = (clone $query)->count();
        $truncated = $total > $maxRows;

        return [$query->limit($maxRows)->get(), $truncated];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQueryForMail(int $mailId, array $filters): Builder
    {
        $query = MailRecipient::query()
            ->with(['user'])
            ->where('mail_id', $mailId)
            ->when(
                filled($filters['filters']['status'] ?? null),
                fn ($query) => $query->where('status', $filters['filters']['status'])
            );

        ListingFilterRules::applySort($query, $filters, [], 'created_at');

        return $query;
    }

    public function dashboardStats(): array
    {
        return [
            'total_sent' => (int) MailRecipient::query()->where('status', MailRecipientStatusEnum::SENT->value)->count(),
            'total_opened' => (int) MailRecipient::query()->whereNotNull('opened_at')->count(),
            'total_failed' => (int) MailRecipient::query()->where('status', MailRecipientStatusEnum::FAILED->value)->count(),
        ];
    }

    public function universityBreakdown(int $mailId): Collection
    {
        return MailRecipient::query()
            ->join('users', 'users.id', '=', 'mail_recipients.user_id')
            ->where('mail_recipients.mail_id', $mailId)
            ->selectRaw('users.university as university, count(*) as total')
            ->whereNotNull('users.university')
            ->groupBy('users.university')
            ->pluck('total', 'university');
    }

    public function openTrend(int $days): Collection
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        return MailRecipient::query()
            ->whereNotNull('opened_at')
            ->where('opened_at', '>=', $from)
            ->selectRaw('DATE(opened_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    public function sendTrend(int $days): Collection
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        return MailRecipient::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $from)
            ->selectRaw('DATE(sent_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    public function statsInRange(?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $scoped = fn () => MailRecipient::query()
            ->join('mails', 'mails.id', '=', 'mail_recipients.mail_id')
            ->when($start !== null, fn ($query) => $query->where('mails.created_at', '>=', $start))
            ->when($end !== null, fn ($query) => $query->where('mails.created_at', '<=', $end));

        $emails = $scoped()->pluck('mail_recipients.email');

        return [
            'total_reach' => (int) $scoped()->count(),
            'total_sent' => (int) $scoped()->where('mail_recipients.status', MailRecipientStatusEnum::SENT->value)->count(),
            'total_opened' => (int) $scoped()->whereNotNull('mail_recipients.opened_at')->count(),
            'total_unsubscribed' => $emails->isEmpty() ? 0 : (int) EmailUnsubscribe::query()->whereIn('email', $emails)->count(),
        ];
    }

    public function unsubscribedCountForMail(int $mailId): int
    {
        $emails = MailRecipient::query()->where('mail_id', $mailId)->pluck('email');

        if ($emails->isEmpty()) {
            return 0;
        }

        return (int) EmailUnsubscribe::query()->whereIn('email', $emails)->count();
    }
}
