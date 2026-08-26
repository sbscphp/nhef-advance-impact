<?php

namespace App\Repositories\Contracts\Mentorship;

use App\Models\MenteeProfile;
use Illuminate\Pagination\LengthAwarePaginator;

interface MenteeProfileRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MenteeProfile;

    public function findByUuid(string $uuid): ?MenteeProfile;

    public function findByUserId(int $userId): ?MenteeProfile;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * Mentees with no currently active match, for the admin Matching Engine.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginateUnmatched(array $filters, int $perPage): LengthAwarePaginator;
}
