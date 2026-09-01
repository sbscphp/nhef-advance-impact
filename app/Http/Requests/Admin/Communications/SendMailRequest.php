<?php

namespace App\Http\Requests\Admin\Communications;

use App\Http\Requests\ApiFormRequest;

class SendMailRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Omitted or in the past: send now. In the future: schedule for that time.
            'send_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
