<?php

namespace App\Repositories\Contracts\Communications;

use App\Models\MailRecipient;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MailRecipientRepositoryInterface
{
    /**
     * Deletes any existing rows for the mail and creates a fresh `pending` row per recipient.
     *
     * @param  list<array{user_id: int|null, email: string}>  $recipients
     * @return Collection<int, MailRecipient>
     */
    public function replaceForMail(int $mailId, array $recipients): Collection;

    /**
     * @return Collection<int, MailRecipient>
     */
    public function listForMail(int $mailId): Collection;

    public function findByUuid(string $uuid): ?MailRecipient;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MailRecipient $recipient, array $data): MailRecipient;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForMail(int $mailId, array $filters, int $perPage): LengthAwarePaginator;

    /**
     * The same filters as paginateForMail(), unpaginated and capped at $maxRows, for CSV/PDF export.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, MailRecipient>, 1: bool}
     */
    public function exportCollectionForMail(int $mailId, array $filters, int $maxRows): array;

    /**
     * @return array{total_sent: int, total_opened: int, total_failed: int}
     */
    public function dashboardStats(): array;

    /**
     * Reach/delivery/open/unsubscribe counts for recipients of mails created within the window
     * (either bound may be null for "no lower/upper limit"), for the dashboard "Overview" cards.
     *
     * @return array{total_reach: int, total_sent: int, total_opened: int, total_unsubscribed: int}
     */
    public function statsInRange(?CarbonInterface $start, ?CarbonInterface $end): array;

    /** How many of one mail's recipients have since unsubscribed (may have unsubscribed after this mail sent). */
    public function unsubscribedCountForMail(int $mailId): int;

    /**
     * Date (Y-m-d) => send count, for the last N days, for the engagement chart's "Send Rate" line.
     *
     * @return Collection<string, int>
     */
    public function sendTrend(int $days): Collection;

    /**
     * University => recipient count, for the audience breakdown widget.
     *
     * @return Collection<string, int>
     */
    public function universityBreakdown(int $mailId): Collection;

    /**
     * Date (Y-m-d) => open count, for the last N days, for the engagement chart.
     *
     * @return Collection<string, int>
     */
    public function openTrend(int $days): Collection;
}
