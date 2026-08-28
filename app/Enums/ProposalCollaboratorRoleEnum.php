<?php

namespace App\Enums;

enum ProposalCollaboratorRoleEnum: string
{
    case OWNER = 'owner';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function canEdit(): bool
    {
        return $this === self::OWNER || $this === self::EDITOR;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Roles assignable via the invite form; `owner` is only ever set implicitly on create. */
    public static function invitable(): array
    {
        return [self::EDITOR->value, self::VIEWER->value];
    }
}
