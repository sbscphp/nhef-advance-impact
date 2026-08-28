<?php

namespace App\Http\Requests\Admin\Crm;

use App\Enums\ProspectInviteTypeEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SendProspectInviteRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'time' => ['required', 'date_format:H:i'],
            'invite_type' => ['required', Rule::in(ProspectInviteTypeEnum::values())],
            'virtual_link' => ['required_if:invite_type,'.ProspectInviteTypeEnum::ONLINE->value, 'nullable', 'url'],
            'venue' => ['required_if:invite_type,'.ProspectInviteTypeEnum::PHYSICAL->value, 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'virtual_link.required_if' => 'Please provide a meeting link for an online invite.',
            'venue.required_if' => 'Please provide a venue for a physical invite.',
        ]);
    }
}
