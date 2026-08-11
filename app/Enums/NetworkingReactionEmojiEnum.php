<?php

namespace App\Enums;

use App\Http\Requests\Customer\Networking\ReactionRequest;

/**
 * A closed set of reactions, matched against the Figma message-reaction bubbles, so that
 * {@see ReactionRequest} can validate against
 * Rule::in(self::values()) rather than accepting arbitrary emoji strings.
 */
enum NetworkingReactionEmojiEnum: string
{
    case LIKE = 'like';
    case LOVE = 'love';
    case FIRE = 'fire';
    case CLAP = 'clap';
    case LAUGH = 'laugh';
    case SAD = 'sad';

    public function symbol(): string
    {
        return match ($this) {
            self::LIKE => '👍',
            self::LOVE => '❤️',
            self::FIRE => '🔥',
            self::CLAP => '👏',
            self::LAUGH => '😂',
            self::SAD => '😢',
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
