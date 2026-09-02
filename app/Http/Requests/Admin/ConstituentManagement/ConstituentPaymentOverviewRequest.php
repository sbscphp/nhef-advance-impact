<?php

namespace App\Http\Requests\Admin\ConstituentManagement;

use App\Http\Requests\ApiFormRequest;

class ConstituentPaymentOverviewRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
