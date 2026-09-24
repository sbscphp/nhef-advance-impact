<?php

namespace App\Http\Resources\Admin\ConstituentManagement;

use App\Http\Resources\TertiaryInstitutionResource;
use App\Models\TertiaryInstitution;
use Illuminate\Http\Request;

/** @mixin TertiaryInstitution */
class TertiaryInstitutionOptionResource extends TertiaryInstitutionResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'already_invited' => (bool) $this->resource->getAttribute('already_invited'),
        ]);
    }
}
