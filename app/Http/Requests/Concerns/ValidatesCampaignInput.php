<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;

/** Shared by the create/update campaign requests: cover photo or video, and the "assign to" officer field. */
trait ValidatesCampaignInput
{
    private const IMAGE_MIME_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    private const VIDEO_MIME_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];

    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    private const MAX_VIDEO_BYTES = 30 * 1024 * 1024;

    /** `allocated_admin_id` is the old name for `assigned_admin_id`; accept it so existing clients keep working. */
    protected function mergeAssigneeAlias(): void
    {
        if (! $this->filled('assigned_admin_id') && $this->filled('allocated_admin_id')) {
            $this->merge(['assigned_admin_id' => $this->input('allocated_admin_id')]);
        }
    }

    /**
     * Multipart clients can't nest arrays, so they may send these as JSON-encoded strings instead.
     *
     * @param  list<string>  $keys
     */
    protected function decodeJsonInputs(array $keys): void
    {
        foreach ($keys as $key) {
            if (is_string($this->input($key))) {
                $decoded = json_decode((string) $this->input($key), true);
                if (is_array($decoded)) {
                    $this->merge([$key => $decoded]);
                }
            }
        }
    }

    /**
     * The "Add Project" cards plus the countdown lead time; `projects.*.uuid` lets an edit keep
     * an existing project. A project here is purely a name/description grouping; its goal lives
     * on each institution's `project_goals` instead.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function projectAndTimerRules(): array
    {
        return [
            'timer_lead_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:720'],
            'projects' => ['sometimes', 'array'],
            'projects.*.uuid' => ['sometimes', 'nullable', 'uuid'],
            'projects.*.name' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /** @return array<string, string> */
    protected function projectMessages(): array
    {
        return [
            'projects.*.name.required' => 'Please give each project a name.',
            'timer_lead_hours.max' => 'The countdown lead time cannot exceed 720 hours (30 days).',
        ];
    }

    /**
     * An institution's NGD goal is broken down per project, by name (it may be created in the
     * same request, so there's no uuid to reference); resolveProjectGoals() sums these lines.
     *
     * @param  string  $prefix  '' for a single institution's own payload, or 'institutions.*.'
     *                          when nested under a list of institutions.
     * @param  bool  $required  False for a partial update where project_goals is optional
     *                          (e.g. updating just the bank account, goals left untouched).
     * @return array<string, array<int, mixed>>
     */
    protected function projectGoalRules(string $prefix = '', bool $required = true): array
    {
        return [
            // Not Laravel's `distinct` rule: with two wildcards in the key, `distinct` checks
            // uniqueness across every institution combined. This closure checks uniqueness
            // within this one project_goals array only.
            $prefix.'project_goals' => [
                $required ? 'required' : 'sometimes',
                'array',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }

                    $names = array_map(
                        fn ($entry) => strtolower(trim((string) (is_array($entry) ? ($entry['project_name'] ?? '') : ''))),
                        $value
                    );

                    if (count($names) !== count(array_unique($names))) {
                        $fail('Each project can only be given one goal per institution.');
                    }
                },
            ],
            $prefix.'project_goals.*.project_name' => ['required', 'string', 'max:255'],
            $prefix.'project_goals.*.goal_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @param  string  $prefix  Must match the prefix passed to {@see self::projectGoalRules()}.
     * @return array<string, string>
     */
    protected function projectGoalMessages(string $prefix = ''): array
    {
        return [
            $prefix.'project_goals.required' => 'Please set at least one project goal for this institution.',
            $prefix.'project_goals.*.project_name.required' => 'Please name the project this goal is for.',
            $prefix.'project_goals.*.goal_amount.required' => 'Please set a goal amount for this project.',
        ];
    }

    protected function campaignCoverRule(bool $nullable = false): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($nullable): void {
            if ($value === null && $nullable) {
                return;
            }

            if ($value instanceof UploadedFile) {
                if (! $value->isValid()) {
                    $fail('The cover upload failed. Please try again.');

                    return;
                }

                $mime = $value->getMimeType();

                if (in_array($mime, self::VIDEO_MIME_TYPES, true)) {
                    if ($value->getSize() > self::MAX_VIDEO_BYTES) {
                        $fail('The cover video must not be larger than 30MB.');
                    }

                    return;
                }

                if (! in_array($mime, self::IMAGE_MIME_TYPES, true)) {
                    $fail('The cover must be a JPG, PNG, GIF, or WEBP image, or an MP4, WEBM, or MOV video.');

                    return;
                }

                if ($value->getSize() > self::MAX_IMAGE_BYTES) {
                    $fail('The cover photo must not be larger than 10MB.');
                }

                return;
            }

            // Accepts a base64/data-URI string or an existing http(s) URL (see FileUploadHelper).
            if (! is_string($value) || trim($value) === '') {
                $fail('The cover must be an uploaded photo or video, a URL, or a base64-encoded image.');
            }
        };
    }
}
