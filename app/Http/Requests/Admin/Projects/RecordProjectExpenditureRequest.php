<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesFileUploads;

class RecordProjectExpenditureRequest extends ApiFormRequest
{
    use ValidatesFileUploads;

    private const ALLOWED_EVIDENCE_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg', 'image/jpg', 'image/png',
    ];

    private const MAX_EVIDENCE_BYTES = 10 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'budget_line_uuid' => ['required', 'uuid', 'exists:project_budget_lines,uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'transaction_date' => ['required', 'date'],
            'reference_id' => ['required', 'string', 'max:255', 'unique:project_expenditures,reference_id'],
            'evidence_url' => ['nullable', $this->fileUploadRule(self::ALLOWED_EVIDENCE_MIME_TYPES, self::MAX_EVIDENCE_BYTES, 'evidence file')],
        ];
    }
}
