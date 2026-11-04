<?php

namespace App\Repositories\Settings;

use App\Models\InstitutionWelcomeMessage;
use App\Repositories\Contracts\Settings\InstitutionWelcomeMessageRepositoryInterface;

class InstitutionWelcomeMessageRepository implements InstitutionWelcomeMessageRepositoryInterface
{
    public function findForCurrentInstitution(): ?InstitutionWelcomeMessage
    {
        return InstitutionWelcomeMessage::query()->first();
    }

    public function upsertForCurrentInstitution(array $data): InstitutionWelcomeMessage
    {
        $existing = $this->findForCurrentInstitution();

        if ($existing instanceof InstitutionWelcomeMessage) {
            $existing->forceFill($data)->save();

            return $existing;
        }

        return InstitutionWelcomeMessage::create($data);
    }
}
