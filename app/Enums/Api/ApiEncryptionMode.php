<?php

namespace App\Enums\Api;

/**
 * API transport encryption policy for a consumer (see RequestResponseEncryptionMiddleware).
 */
enum ApiEncryptionMode: string
{
    /** Decrypt inbound payloads and encrypt outbound JSON bodies (full envelope). */
    case Both = 'both';

    /** Decrypt inbound where applicable; responses remain plaintext JSON. */
    case RequestOnly = 'request_only';

    /** Inbound payloads are treated as plaintext JSON / query; outbound bodies are encrypted. */
    case ResponseOnly = 'response_only';

    public function decryptsInbound(): bool
    {
        return match ($this) {
            self::Both, self::RequestOnly => true,
            self::ResponseOnly => false,
        };
    }

    public function encryptsOutbound(): bool
    {
        return match ($this) {
            self::Both, self::ResponseOnly => true,
            self::RequestOnly => false,
        };
    }

    public static function tryFromConfig(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
