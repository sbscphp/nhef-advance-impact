<?php

namespace App\Http\Requests\Admin\Projects;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesFileUploads;

class UpdateProjectImpactReportRequest extends ApiFormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'report_date' => ['sometimes', 'date'],
            'deliverable_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:project_deliverables,uuid'],
            'description' => ['sometimes', 'string'],
            'budget_line_uuid' => ['sometimes', 'uuid', 'exists:project_budget_lines,uuid'],
            'expenditure_value' => ['sometimes', 'numeric', 'min:0'],
            'evidence_urls' => ['sometimes', 'nullable', 'array', 'max:3'],
            'evidence_urls.*' => [$this->fileUploadRule(self::ALLOWED_EVIDENCE_MIME_TYPES, self::MAX_EVIDENCE_BYTES, 'evidence file')],
        ];
    }
}
