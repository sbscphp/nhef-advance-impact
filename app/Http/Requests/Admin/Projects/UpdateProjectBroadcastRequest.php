<?php

namespace App\Http\Requests\Admin\Projects;

use App\Enums\ProjectBroadcastDeliveryEnum;
use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\ValidatesFileUploads;
use Illuminate\Validation\Rule;

class UpdateProjectBroadcastRequest extends ApiFormRequest
{
    use ValidatesFileUploads;

    private const ALLOWED_ATTACHMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'image/jpeg', 'image/jpg', 'image/png',
    ];

    private const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'send_date' => ['sometimes', 'date'],
            'delivery_via' => ['sometimes', Rule::in(ProjectBroadcastDeliveryEnum::values())],
            'message' => ['sometimes', 'string'],
            'all_team_members' => ['sometimes', 'boolean'],
            'recipient_admin_uuids' => ['sometimes', 'required_if:all_team_members,false', 'array'],
            'recipient_admin_uuids.*' => ['uuid', 'exists:admins,uuid'],
            'attachment_urls' => ['sometimes', 'nullable', 'array', 'max:3'],
            'attachment_urls.*' => [$this->fileUploadRule(self::ALLOWED_ATTACHMENT_MIME_TYPES, self::MAX_ATTACHMENT_BYTES, 'attachment file')],
        ];
    }
}
