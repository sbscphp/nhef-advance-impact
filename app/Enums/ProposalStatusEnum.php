<?php

namespace App\Enums;

enum ProposalStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case SENT = 'sent';
    case ACTIVE = 'active';
    case FAILED = 'failed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
