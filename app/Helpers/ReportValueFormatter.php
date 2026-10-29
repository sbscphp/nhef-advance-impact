<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a report dataset's raw cell value (an ISO 8601 timestamp, a PHP bool, a snake_case enum
 * value, etc.) into the human-readable text a downloaded CSV/PDF report should show, based on the
 * field's declared type (see each dataset's nativeFields()). Applied only at the CSV/PDF export
 * boundary, never to the JSON preview the admin frontend renders and formats itself.
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
            // "number" deliberately falls through to formatString(): these fields mix years,
            // percentages, counts, and money amounts with no way to tell them apart here, and
            // thousand-separating a year (e.g. "2,018") reads as more broken, not less. A plain
            // numeric value never matches formatString()'s snake_case pattern, so it passes through
            // unchanged either way.
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

    /** Humanizes a raw snake_case/lowercase token (e.g. an enum value like "one_time" or "pending")
     * into Title Case words; leaves names, emails, titles, and codes (NGN, TRN-ABC123) untouched. */
    private static function formatString(string $value): string
    {
        return preg_match('/^[a-z0-9]+(_[a-z0-9]+)*$/', $value) === 1
            ? Str::title(str_replace('_', ' ', $value))
            : $value;
    }
}
