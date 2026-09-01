<?php

namespace App\Repositories\Contracts\Communications;

use App\Models\Mail;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MailRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Mail;

    public function findByUuid(string $uuid): ?Mail;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Mail $mail, array $data): Mail;

    public function delete(Mail $mail): void;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array;

    /** Mails created within the window (either bound may be null), for the dashboard "Total Mails" card. */
    public function countInRange(?CarbonInterface $start, ?CarbonInterface $end): int;

    /**
     * The same filters as paginate(), unpaginated and capped at $maxRows, for CSV/PDF export.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: Collection<int, Mail>, 1: bool}
     */
    public function exportCollection(array $filters, int $maxRows): array;
}
