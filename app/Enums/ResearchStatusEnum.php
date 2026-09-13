<?php

namespace App\Enums;

enum ResearchStatusEnum: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::COMPLETED => 'Completed',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
