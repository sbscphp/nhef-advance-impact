<?php

namespace App\Services\Settings;

use App\Enums\AuditActionEnum;
use App\Enums\ModuleEnums;
use App\Enums\UserTypeEnum;
use App\Helpers\FileUploadHelper;
use App\Helpers\GeneralHelper;
use App\Models\Admin;
use App\Models\InstitutionWelcomeMessage;
use App\Repositories\Contracts\Settings\InstitutionWelcomeMessageRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WelcomeMessageService
{
    private const CONTENT_FIELDS = [
        'heading', 'body', 'signer_name', 'signer_title', 'tagline', 'tagline_emphasis',
    ];

    private const FILE_FIELDS = ['banner' => 'banner_url', 'photo' => 'photo_url', 'signature' => 'signature_url'];

    public function __construct(
        private readonly InstitutionWelcomeMessageRepositoryInterface $welcomeMessageRepository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forAdmin(Admin $actor): array
    {
        return $this->present($this->welcomeMessageRepository->findForCurrentInstitution(), $actor);
    }

    /**
     * `visible` (the viewing admin's own dismissed state, not institution content) is handled
     * entirely separately from the content fields below, since it's a different kind of change -
     * dismissing doesn't touch the institution's own saved message at all.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function update(array $payload, Admin $actor, Request $request): array
    {
        $hasContentChange = collect(self::CONTENT_FIELDS)->contains(fn ($field) => array_key_exists($field, $payload))
            || collect(array_keys(self::FILE_FIELDS))->contains(fn ($field) => array_key_exists($field, $payload));

        // Resolved before any write, including the unrelated `visible` toggle below: a bad
        // banner/photo/signature value throws here, and the whole request should fail cleanly
        // with nothing saved - not dismiss the message for this admin while also 422ing.
        $data = [];

        if ($hasContentChange) {
            foreach (self::CONTENT_FIELDS as $field) {
                if (array_key_exists($field, $payload)) {
                    $data[$field] = $payload[$field];
                }
            }

            foreach (self::FILE_FIELDS as $inputKey => $column) {
                if (array_key_exists($inputKey, $payload)) {
                    $data[$column] = FileUploadHelper::smartSingleFileUpload($payload[$inputKey], 'settings/welcome-message');
                }
            }
        }

        DB::transaction(function () use ($payload, $actor, $request, $hasContentChange, $data): void {
            if (array_key_exists('visible', $payload)) {
                $actor->forceFill([
                    'welcome_message_dismissed_at' => $payload['visible'] ? null : now(),
                ])->save();
            }

            if ($hasContentChange) {
                $message = $this->welcomeMessageRepository->upsertForCurrentInstitution($data);

                GeneralHelper::storeAuditLog(
                    UserTypeEnum::ADMIN,
                    AuditActionEnum::WELCOME_MESSAGE_UPDATED,
                    $request,
                    $actor->uuid,
                    ['welcome_message_uuid' => $message->uuid],
                    $actor->displayName().' updated the dashboard welcome message.',
                    InstitutionWelcomeMessage::class,
                    $message->uuid,
                    ModuleEnums::dashboard,
                    200,
                );
            }
        });

        return $this->forAdmin($actor->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function present(?InstitutionWelcomeMessage $message, Admin $actor): array
    {
        return [
            'visible' => $actor->welcome_message_dismissed_at === null,
            'configured' => $message instanceof InstitutionWelcomeMessage,
            'heading' => $message->heading ?? null,
            'body' => $message->body ?? null,
            'banner_url' => $message->banner_url ?? null,
            'photo_url' => $message->photo_url ?? null,
            'signature_url' => $message->signature_url ?? null,
            'signer_name' => $message->signer_name ?? null,
            'signer_title' => $message->signer_title ?? null,
            'tagline' => $message->tagline ?? null,
            'tagline_emphasis' => $message->tagline_emphasis ?? null,
        ];
    }
}
