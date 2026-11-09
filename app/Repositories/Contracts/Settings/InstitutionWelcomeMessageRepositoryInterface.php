<?php

namespace App\Repositories\Contracts\Settings;

use App\Models\InstitutionWelcomeMessage;

interface InstitutionWelcomeMessageRepositoryInterface
{
    /** Scoped to the current tenant via the model's own BelongsToTenant. */
    public function findForCurrentInstitution(): ?InstitutionWelcomeMessage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertForCurrentInstitution(array $data): InstitutionWelcomeMessage;
}
