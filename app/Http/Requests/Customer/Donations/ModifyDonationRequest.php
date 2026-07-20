<?php

namespace App\Http\Requests\Customer\Donations;

use App\Enums\DonationFrequencyEnum;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ModifyDonationRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'required_without:frequency', 'numeric', 'min:100'],
            // A recurring donation can only be modified to another recurring cadence — not
            // collapsed to one_time via Modify (use Cancel to stop recurring altogether).
            'frequency' => [
                'sometimes',
                'required_without:amount',
                Rule::in([
                    DonationFrequencyEnum::MONTHLY->value,
                    DonationFrequencyEnum::QUARTERLY->value,
                    DonationFrequencyEnum::ANNUALLY->value,
                ]),
            ],
        ];
    }
}
