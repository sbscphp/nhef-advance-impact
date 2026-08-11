<?php

namespace App\Enums;

/**
 * A direct channel always has exactly two members and is created implicitly the first time
 * one user messages another. Community and Forum channels are admin-created (seeded for now;
 * see database/seeders/NetworkingSeeder.php) and support browse/join/leave.
 */
enum NetworkingChannelTypeEnum: string
{
    case DIRECT = 'direct';
    case COMMUNITY = 'community';
    case FORUM = 'forum';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Non-direct types: the ones with a name/description that can be browsed and joined.
     *
     * @return list<string>
     */
    public static function browsableValues(): array
    {
        return [self::COMMUNITY->value, self::FORUM->value];
    }
}
