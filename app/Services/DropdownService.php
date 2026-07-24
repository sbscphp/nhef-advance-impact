<?php

namespace App\Services;

use App\Enums\DegreeEnum;
use App\Enums\EmploymentStatusEnum;
use App\Enums\OtpChannelEnum;
use App\Http\Resources\CountryResource;
use App\Models\Country;

final class DropdownService
{
    /**
     * Earliest graduation year offered on the sign-up dropdown.
     */
    private const GRADUATION_YEAR_FLOOR = 1960;

    /**
     * Options exposed on customer registration metadata (sign-up dropdowns).
     *
     * @return array{
     *     countries: array<int, array<string, mixed>>,
     *     otp_channels: list<array{value: string, label: string}>,
     *     degrees: list<array{value: string, label: string}>,
     *     employment_statuses: list<array{value: string, label: string}>,
     *     graduation_years: list<int>,
     *     default_country_uuid: string,
     *     default_dial_code: string,
     * }
     */
    public function customerRegistrationMetadata(): array
    {
        $defaultCountry = Country::defaultCountry();

        return [
            'countries' => CountryResource::collection(
                Country::query()->active()->orderBy('name')->get()
            )->resolve(),
            'otp_channels' => [
                ['value' => OtpChannelEnum::EMAIL->value, 'label' => 'Email'],
                ['value' => OtpChannelEnum::SMS->value, 'label' => 'SMS'],
            ],
            'degrees' => array_map(
                static fn (DegreeEnum $degree): array => ['value' => $degree->value, 'label' => $degree->value],
                DegreeEnum::cases()
            ),
            'employment_statuses' => array_map(
                static fn (EmploymentStatusEnum $status): array => ['value' => $status->value, 'label' => $status->value],
                EmploymentStatusEnum::cases()
            ),
            'graduation_years' => $this->graduationYears(),
            'default_country_uuid' => $defaultCountry->uuid,
            'default_dial_code' => $defaultCountry->dial_code,
        ];
    }

    /**
     * @return list<int>
     */
    private function graduationYears(): array
    {
        $currentYear = (int) now()->year;

        return range($currentYear, self::GRADUATION_YEAR_FLOOR);
    }
}
