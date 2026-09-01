<?php

namespace App\Repositories\Contracts\Communications;

use App\Models\EmailUnsubscribe;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface EmailUnsubscribeRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmailUnsubscribe;

    public function isUnsubscribed(string $email): bool;

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public function unsubscribedEmails(array $emails): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Most recently unsubscribed, for the Mails dashboard preview panel.
     *
     * @return Collection<int, EmailUnsubscribe>
     */
    public function recent(int $limit): Collection;

    /**
     * The same filters as paginate(), unpaginated and capped at $maxRows, for CSV/PDF export.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, EmailUnsubscribe>, 1: bool}
     */
    public function exportCollection(array $filters, int $maxRows): array;
}
