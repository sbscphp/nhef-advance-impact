<?php

namespace App\Support;

/**
 * Mirrors {@see SmsMode}: controls whether payment initialization/verification hits a real
 * gateway or is simulated for local development and Postman testing.
 */
final class PaymentMode
{
    public const STUB = 'stub';

    public const LOG = 'log';

    public const LIVE = 'live';

    private static ?string $override = null;

    public static function current(): string
    {
        if (self::$override !== null) {
            return self::$override;
        }

        $mode = strtolower(trim((string) env('PAYMENT_MODE', self::STUB)));

        if (in_array($mode, [self::STUB, self::LOG, self::LIVE], true)) {
            return $mode;
        }

        return self::STUB;
    }

    public static function setOverride(?string $mode): void
    {
        self::$override = $mode;
    }

    public static function isLive(): bool
    {
        return self::current() === self::LIVE;
    }
}
