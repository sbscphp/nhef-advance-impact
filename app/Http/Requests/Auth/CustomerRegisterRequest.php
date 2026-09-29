<?php

namespace App\Http\Requests\Auth;

use App\Enums\ConstituentTypeEnum;
use App\Enums\DegreeEnum;
use App\Enums\eClientType;
use App\Enums\EmploymentStatusEnum;
use App\Http\Requests\ApiFormRequest;
use App\Models\Country;
use App\Services\Phone\PhoneNumberService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * One shared endpoint for all three "Sign Up as" flavours (Alumni, Non-Alumni, Organisation);
 * `constituent_type` decides which of the type-specific field groups below are required. An
 * Organisation account has no personal name at all (see the "Sign Up - organisation" Figma
 * screen), so firstname/lastname are nullable in the DB and prohibited for that type.
 */
class CustomerRegisterRequest extends ApiFormRequest
{
    private const NAME_REGEX = '/^[\p{L}\'\-]+(?:\s[\p{L}\'\-]+)*$/u';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            $this->commonRules(),
            $this->personOrOrganizationRules(),
            $this->alumniRules(),
            $this->nonAlumniRules(),
            $this->organizationRules(),
        );
    }

    private function isType(ConstituentTypeEnum $type): bool
    {
        return $this->input('constituent_type') === $type->value;
    }

    /** Fields every "Sign Up as" flavour collects. */
    private function commonRules(): array
    {
        return [
            'constituent_type' => ['required', Rule::in(ConstituentTypeEnum::values())],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'phone_number' => ['required', 'string', 'regex:/^\+\d{8,15}$/'],
            'country_uuid' => ['nullable', 'uuid', Rule::exists('countries', 'uuid')->where('is_active', true)],
            'country_code' => ['nullable', 'string', 'max:10'],
            'client' => ['nullable', Rule::in(eClientType::values())],
            // Required across all three "Sign Up as" flavours (each Figma form shows it required).
            'tertiary_institution_uuid' => ['required', 'uuid', Rule::exists('tertiary_institutions', 'uuid')],
        ];
    }

    /**
     * Alumni and Non-Alumni are people, with a personal name and their own (optional) employer;
     * Organisation is an entity with no personal name field, whose own name is required instead.
     */
    private function personOrOrganizationRules(): array
    {
        $isPerson = ! $this->isType(ConstituentTypeEnum::ORGANIZATION);

        return [
            'firstname' => [Rule::requiredIf($isPerson), 'prohibited_if:constituent_type,organization', 'string', 'min:2', 'max:50', 'regex:'.self::NAME_REGEX],
            'lastname' => [Rule::requiredIf($isPerson), 'prohibited_if:constituent_type,organization', 'string', 'min:2', 'max:50', 'regex:'.self::NAME_REGEX],
            'employment_status' => [Rule::requiredIf($isPerson), 'prohibited_if:constituent_type,organization', Rule::in(EmploymentStatusEnum::values())],
            'position' => ['nullable', 'string', 'max:255', 'prohibited_if:constituent_type,organization'],
            // Alumni/Non-Alumni's own current employer (optional); for Organisation this is instead
            // the organisation's own required name.
            'organisation_name' => [Rule::requiredIf($this->isType(ConstituentTypeEnum::ORGANIZATION)), 'string', 'max:255'],
        ];
    }

    private function alumniRules(): array
    {
        $isAlumni = $this->isType(ConstituentTypeEnum::ALUMNI);

        return [
            'matric_no' => ['nullable', 'string', 'max:50', 'prohibited_unless:constituent_type,alumni'],
            'department' => [Rule::requiredIf($isAlumni), 'prohibited_unless:constituent_type,alumni', 'string', 'max:255'],
            'year_of_graduation' => [Rule::requiredIf($isAlumni), 'prohibited_unless:constituent_type,alumni', 'integer', 'min:1960', 'max:'.now()->year],
            'degree_earned' => [Rule::requiredIf($isAlumni), 'prohibited_unless:constituent_type,alumni', Rule::in(DegreeEnum::values())],
        ];
    }

    private function nonAlumniRules(): array
    {
        $isNonAlumni = $this->isType(ConstituentTypeEnum::NON_ALUMNI);
        // Sector of employment only makes sense for someone currently working.
        $isCurrentlyWorking = in_array($this->input('employment_status'), [
            EmploymentStatusEnum::EMPLOYED->value,
            EmploymentStatusEnum::SELF_EMPLOYED->value,
        ], true);

        return [
            'gender' => [Rule::requiredIf($isNonAlumni), 'prohibited_unless:constituent_type,non_alumni', 'string', 'max:50'],
            'date_of_birth' => [Rule::requiredIf($isNonAlumni), 'prohibited_unless:constituent_type,non_alumni', 'date', 'before:today'],
            'country_of_residence_uuid' => [Rule::requiredIf($isNonAlumni), 'prohibited_unless:constituent_type,non_alumni', 'uuid', Rule::exists('countries', 'uuid')->where('is_active', true)],
            'sector_of_employment' => [Rule::requiredIf($isNonAlumni && $isCurrentlyWorking), 'prohibited_unless:constituent_type,non_alumni', 'nullable', 'string', 'max:255'],
            // No fixed option list exists yet for this multi-select; accepted as free text for now.
            'area_of_interest' => [Rule::requiredIf($isNonAlumni), 'prohibited_unless:constituent_type,non_alumni', 'array', 'min:1'],
            'area_of_interest.*' => ['string', 'max:255'],
            'address' => [Rule::requiredIf($isNonAlumni), 'prohibited_if:constituent_type,alumni', 'string', 'max:500'],
        ];
    }

    private function organizationRules(): array
    {
        $isOrganization = $this->isType(ConstituentTypeEnum::ORGANIZATION);

        return [
            'organisation_type' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'string', 'max:255'],
            'organisation_description' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'string', 'max:2000'],
            // Figma's own label says "(if any)", so this stays optional despite the field's asterisk.
            'rc_number' => ['nullable', 'string', 'max:100', 'prohibited_unless:constituent_type,organization'],
            'date_of_incorporation' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'date', 'before_or_equal:today'],
            'website' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'url', 'max:255'],
            'country_of_operation_uuid' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'uuid', Rule::exists('countries', 'uuid')->where('is_active', true)],
            'organisation_size' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'string', 'max:100'],
            'sector_of_operation' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'string', 'max:255'],
            // No fixed option list exists yet for this multi-select; accepted as free text for now.
            'engagement_preference' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'array', 'min:1'],
            'engagement_preference.*' => ['string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'An account with this email already exists. Would you like to log in instead, or use a different email?',
            'phone_number.regex' => 'Please enter a valid phone number for the selected country.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'firstname' => trim((string) $this->input('firstname')),
            'lastname' => trim((string) $this->input('lastname')),
        ]);

        $country = Country::forRegistration($this->input('country_uuid'));

        $normalized = app(PhoneNumberService::class)->normalize(
            (string) $this->input('phone_number'),
            $country,
        );

        if ($normalized !== null) {
            $this->merge($normalized);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $phoneNumber = (string) $this->input('phone_number');

            if (app(PhoneNumberService::class)->isRegistered($phoneNumber)) {
                $validator->errors()->add(
                    'phone_number',
                    'An account with this phone number already exists. Would you like to log in instead, or use a different phone number?',
                );
            }
        });
    }
}
