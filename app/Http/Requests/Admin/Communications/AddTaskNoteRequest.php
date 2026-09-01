<?php

namespace App\Http\Requests\Admin\Communications;

use App\Http\Requests\ApiFormRequest;

class AddTaskNoteRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
        ];
    }
}
