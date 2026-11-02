<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Enums\ConstituentTypeEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * An Organisation has no personal name; its fields match CustomerRegisterRequest's public-signup
 * ones directly, no mapping. `tertiary_institution_uuid` is ignored if sent: institution always
 * resolves from the acting admin's own tenant.
 */
class InviteConstituentRequest extends ApiFormRequest
{
    private function isOrganization(): bool
    {
        return $this->input('constituent_type') === ConstituentTypeEnum::ORGANIZATION->value;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isOrganization = $this->isOrganization();
        $isPerson = ! $isOrganization;

        return [
            'first_name' => [Rule::requiredIf($isPerson), 'prohibited_if:constituent_type,organization', 'string', 'max:255'],
            'last_name' => [Rule::requiredIf($isPerson), 'prohibited_if:constituent_type,organization', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'constituent_type' => ['sometimes', Rule::in(ConstituentTypeEnum::values())],
            'invite_message' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'organisation_name' => [Rule::requiredIf($isOrganization), 'prohibited_unless:constituent_type,organization', 'string', 'max:255'],
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
            'address' => [Rule::requiredIf($isOrganization), 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'A constituent with this email already exists.',
        ]);
    }
}
