<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ListingFilterRules
{
    /**
     * @return list<string>
     */
    public static function periodValues(): array
    {
        return [
            'today',
            'yesterday',
            '1day',
            '3days',
            '7days',
            '14days',
            '30days',
            'quarter',
            '3months',
            '6months',
            '1year',
            'lastyear',
            'custom',
        ];
    }

    /**
     * Human-readable label per {@see self::periodValues()} entry, for a frontend to populate a
     * period picker without hardcoding the list.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function periodOptions(): array
    {
        $labels = [
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            '1day' => 'Last 24 Hours',
            '3days' => 'Last 3 Days',
            '7days' => 'Last 7 Days',
            '14days' => 'Last 14 Days',
            '30days' => 'Last 30 Days',
            'quarter' => 'Last Quarter',
            '3months' => 'Last 3 Months',
            '6months' => 'Last 6 Months',
            '1year' => 'Last 12 Months',
            'lastyear' => 'Last Year',
            'custom' => 'Custom Date',
        ];

        return array_map(
            fn (string $value): array => ['value' => $value, 'label' => $labels[$value]],
            self::periodValues()
        );
    }

    /**
     * Shared query-string rules for search, date range, ordering, and pagination.
     *
     * Domain-specific filters should merge keys such as `filters.*` after calling this method.
     *
     * @param  list<string>  $sortableColumns
     */
    public static function rules(array $sortableColumns, int $maxPerPage = 100): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'period' => ['sometimes', 'nullable', Rule::in(self::periodValues())],
            'start_date' => ['sometimes', 'nullable', 'date', 'required_if:period,custom'],
            'end_date' => ['sometimes', 'nullable', 'date', 'required_if:period,custom', 'after_or_equal:start_date'],
            'sort_by' => ['sometimes', 'nullable', 'string', Rule::in($sortableColumns)],
            'sort_direction' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$maxPerPage],
            'filters' => ['sometimes', 'array'],
        ];
    }

    /**
     * Applies the "Order Arrangement" sort filter. `$sortMap` maps each logical sortable key
     * (e.g. 'name', 'value') to a closure applying the actual ordering, since the underlying
     * column often lives on a related table (e.g. a Pledge's "name" is its campaign's title).
     * Falls back to `$defaultColumn`/`$defaultDirection` when sort_by is absent or unmapped.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, \Closure(Builder|Relation, string): void>  $sortMap
     */
    public static function applySort(Builder|Relation $query, array $validated, array $sortMap, string $defaultColumn = 'created_at', string $defaultDirection = 'desc'): void
    {
        $direction = isset($validated['sort_direction'])
            ? (strtolower((string) $validated['sort_direction']) === 'asc' ? 'asc' : 'desc')
            : $defaultDirection;
        $sortBy = $validated['sort_by'] ?? null;

        if (is_string($sortBy) && isset($sortMap[$sortBy])) {
            $sortMap[$sortBy]($query, $direction);

            return;
        }

        $query->orderBy($defaultColumn, $direction);
    }

    /**
     * Shared rules for date-period based filters.
     *
     * If period=custom, start_date and end_date are required.
     */
    public static function periodDateRules(): array
    {
        return [
            'period' => ['sometimes', 'nullable', Rule::in(self::periodValues())],
            'start_date' => ['sometimes', 'nullable', 'date', 'required_if:period,custom'],
            'end_date' => ['sometimes', 'nullable', 'date', 'required_if:period,custom', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array{start_date: string|null, end_date: string|null}
     */
    public static function dateRangeFromPeriod(?string $period): array
    {
        $value = strtolower((string) $period);
        $now = now();

        [$start, $end] = match ($value) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '1day' => [$now->copy()->subDay()->startOfDay(), $now->copy()->endOfDay()],
            '3days' => [$now->copy()->subDays(3)->startOfDay(), $now->copy()->endOfDay()],
            '7days' => [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()],
            '14days' => [$now->copy()->subDays(14)->startOfDay(), $now->copy()->endOfDay()],
            '30days' => [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()],
            'quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            '3months' => [$now->copy()->subMonths(3)->startOfDay(), $now->copy()->endOfDay()],
            '6months' => [$now->copy()->subMonths(6)->startOfDay(), $now->copy()->endOfDay()],
            '1year' => [$now->copy()->subYear()->startOfDay(), $now->copy()->endOfDay()],
            'lastyear' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [null, null],
        };

        return [
            'start_date' => $start instanceof CarbonInterface ? $start->toDateString() : null,
            'end_date' => $end instanceof CarbonInterface ? $end->toDateString() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{start: Carbon|null, end: Carbon|null, period: string|null}
     */
    public static function resolveDateWindow(array $validated): array
    {
        $period = isset($validated['period']) ? strtolower((string) $validated['period']) : null;
        $start = ! empty($validated['start_date']) ? Carbon::parse((string) $validated['start_date'])->startOfDay() : null;
        $end = ! empty($validated['end_date']) ? Carbon::parse((string) $validated['end_date'])->endOfDay() : null;

        if (($start === null || $end === null) && $period !== null && $period !== '' && $period !== 'custom') {
            $derived = self::dateRangeFromPeriod($period);
            if ($start === null && ! empty($derived['start_date'])) {
                $start = Carbon::parse((string) $derived['start_date'])->startOfDay();
            }
            if ($end === null && ! empty($derived['end_date'])) {
                $end = Carbon::parse((string) $derived['end_date'])->endOfDay();
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'period' => ($period !== null && $period !== '') ? $period : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{period: string|null, start_date: string|null, end_date: string|null}
     */
    public static function periodMeta(array $validated): array
    {
        $window = self::resolveDateWindow($validated);

        return [
            'period' => $window['period'],
            'start_date' => $window['start']?->toDateString(),
            'end_date' => $window['end']?->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function applyResolvedDateRange(Builder|Relation $query, array $validated, string $column = 'created_at'): void
    {
        $window = self::resolveDateWindow($validated);

        if ($window['start'] !== null) {
            $query->where($column, '>=', $window['start']);
        }

        if ($window['end'] !== null) {
            $query->where($column, '<=', $window['end']);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function listingMessages(): array
    {
        return [
            'search.string' => 'Search query must be text.',
            'search.max' => 'Search query may not be longer than 255 characters.',
            'period.in' => 'Period filter is invalid.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.required_if' => 'Start date is required when period is set to "custom".',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.required_if' => 'End date is required when period is set to "custom".',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'sort_by.in' => 'Sort field is invalid.',
            'sort_direction.in' => 'Sort direction must be either "asc" or "desc".',
            'page.integer' => 'Page must be a number.',
            'page.min' => 'Page must be at least 1.',
            'per_page.integer' => 'Per page must be a number.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page may not be greater than the allowed maximum.',
            'filters.array' => 'Filters must be a structured set of values.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function periodDateMessages(): array
    {
        return [
            'period.in' => 'Period filter is invalid.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.required_if' => 'Start date is required when period is set to "custom".',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.required_if' => 'End date is required when period is set to "custom".',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }

    /**
     * @throws ValidationException  If a period preset is combined with an explicit start_date/end_date.
     */
    public static function applyPeriodDateRangeToRequest(Request|FormRequest $request): void
    {
        self::prepareListingRequest($request);

        $period = strtolower((string) $request->input('period', ''));
        if ($period === '' || $period === 'custom') {
            return;
        }

        if (filled($request->input('start_date')) || filled($request->input('end_date'))) {
            throw ValidationException::withMessages([
                'period' => 'Do not send start_date/end_date together with a period preset; use period=custom for a custom range instead.',
            ]);
        }

        $range = self::dateRangeFromPeriod($period);
        if ($range['start_date'] !== null && $range['end_date'] !== null) {
            $request->merge($range);
        }
    }

    public static function prepareListingRequest(Request|FormRequest $request): void
    {
        $merge = [];

        foreach (['search', 'start_date', 'end_date', 'export', 'period', 'sort_by', 'sort_direction', 'per_page'] as $key) {
            if (! $request->query->has($key)) {
                continue;
            }

            $normalized = self::normalizeScalar($request->query($key));
            if ($normalized === null) {
                $merge[$key] = null;
            } elseif ($normalized !== $request->query($key)) {
                $merge[$key] = $normalized;
            }
        }

        $queryFilters = $request->query('filters');
        $bodyFilters = $request->request->get('filters');

        $queryFilters = is_array($queryFilters) ? $queryFilters : [];
        $bodyFilters = is_array($bodyFilters) ? $bodyFilters : [];

        $mergedFilters = $queryFilters;

        foreach ($bodyFilters as $key => $value) {
            $queryValue = $mergedFilters[$key] ?? null;
            if (self::isBlankValue($queryValue) && ! self::isBlankValue($value)) {
                $mergedFilters[$key] = $value;
            }
        }

        foreach ($mergedFilters as $key => $value) {
            $normalized = self::normalizeScalar($value);
            if ($normalized === null) {
                unset($mergedFilters[$key]);
            } else {
                $mergedFilters[$key] = $normalized;
            }
        }

        if ($mergedFilters !== [] || $request->query->has('filters')) {
            $merge['filters'] = $mergedFilters;
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    private static function isBlankValue(mixed $value): bool
    {
        return self::normalizeScalar($value) === null;
    }

    private static function normalizeScalar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $normalized = trim($value, " \t\n\r\0\x0B\"'");

            if ($normalized === '' || strtolower($normalized) === 'null') {
                return null;
            }

            return $normalized;
        }

        return $value;
    }
}
