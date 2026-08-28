<?php

namespace App\Enums;

/** Standard nonprofit donor-pipeline stages (BRD CRM-06). Order drives the Kanban columns and the pipeline stepper's completed/current flags. */
enum ProspectPipelineStageEnum: string
{
    case IDENTIFICATION = 'identification';
    case QUALIFICATION = 'qualification';
    case CULTIVATION = 'cultivation';
    case SOLICITATION = 'solicitation';
    case STEWARDSHIP = 'stewardship';

    public function label(): string
    {
        return match ($this) {
            self::IDENTIFICATION => 'Identification',
            self::QUALIFICATION => 'Qualification',
            self::CULTIVATION => 'Cultivation',
            self::SOLICITATION => 'Solicitation',
            self::STEWARDSHIP => 'Stewardship',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function order(): int
    {
        return array_search($this->value, self::values(), true);
    }
}
