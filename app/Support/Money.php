<?php

namespace App\Support;

/**
 * Human-readable rendering for money amounts stored as decimal strings, e.g. "NGN 300,000.00".
 */
final class Money
{
    public static function format(string|int|float|null $amount, ?string $currency): string
    {
        $formatted = number_format((float) $amount, 2);
        $currency = trim((string) $currency);

        return $currency !== '' ? $currency.' '.$formatted : $formatted;
    }
}
