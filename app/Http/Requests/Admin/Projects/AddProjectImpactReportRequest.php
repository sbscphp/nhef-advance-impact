<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesFileUploads;

class AddProjectImpactReportRequest extends ApiFormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'report_date' => ['required', 'date'],
            'deliverable_uuid' => ['nullable', 'uuid', 'exists:project_deliverables,uuid'],
            'description' => ['required', 'string'],
            'budget_line_uuid' => ['required', 'uuid', 'exists:project_budget_lines,uuid'],
            'expenditure_value' => ['required', 'numeric', 'min:0'],
            'evidence_urls' => ['nullable', 'array', 'max:3'],
            'evidence_urls.*' => [$this->fileUploadRule(self::ALLOWED_EVIDENCE_MIME_TYPES, self::MAX_EVIDENCE_BYTES, 'evidence file')],
        ];
    }
}
