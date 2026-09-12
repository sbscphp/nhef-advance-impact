<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectDocumentCategoryEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesFileUploads;
use Illuminate\Validation\Rule;

class UploadProjectDocumentRequest extends ApiFormRequest
{
    use ValidatesFileUploads;

    private const ALLOWED_DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg', 'image/jpg', 'image/png',
    ];

    private const MAX_DOCUMENT_BYTES = 10 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ProjectDocumentCategoryEnum::values())],
            'file_urls' => ['required', 'array', 'min:1', 'max:3'],
            'file_urls.*' => ['required', $this->fileUploadRule(self::ALLOWED_DOCUMENT_MIME_TYPES, self::MAX_DOCUMENT_BYTES, 'document file')],
        ];
    }
}
