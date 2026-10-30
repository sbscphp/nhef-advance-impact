<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Formats a report cell for CSV/PDF export only, not the admin frontend's JSON preview.
 */
class ReportValueFormatter
{
    public static function format(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'date' => self::formatDate($value),
            'boolean' => self::formatBoolean($value),
            // "number" falls through to formatString(): a plain numeric value never matches its
            // snake_case pattern, so it passes through unchanged.
            default => self::formatString((string) $value),
        };
    }

    private static function formatDate(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value)->format('F j, Y g:i A');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private static function formatBoolean(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
    }

    /** Title-cases a snake_case enum value (e.g. "one_time"); leaves names, emails, and codes untouched. */
    private static function formatString(string $value): string
    {
        return preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $value) === 1
            ? Str::title(str_replace('_', ' ', $value))
            : $value;
    }
}
