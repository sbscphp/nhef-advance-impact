<?php

namespace App\Http\Resources\Concerns;

use App\Models\Campaign;
use App\Models\CampaignProject;
use App\Support\Money;
use App\Support\ViewerVisibility;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shared campaign fields for the admin and public campaign resources: schedule and timer,
 * projects, assignee, and the money that is hidden from viewers without monetary access.
 *
 * @phpstan-require-extends JsonResource
 */
trait PresentsCampaign
{
    private function campaign(): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->resource;

        return $campaign;
    }

    /**
     * Start/end (ISO 8601 with time), the countdown lead time and the derived timer state.
     *
     * @return array<string, mixed>
     */
    protected function schedulePayload(): array
    {
        $campaign = $this->campaign();

        return [
            'starts_at' => $campaign->starts_at?->toIso8601String(),
            'ends_at' => $campaign->ends_at?->toIso8601String(),
            'timer_lead_hours' => $campaign->timer_lead_hours,
            'timer' => $campaign->timer(),
            'display_status' => $campaign->displayStatus(),
        ];
    }

    /**
     * A project is purely a name/description grouping under a campaign (2026-10-20): it no
     * longer carries its own goal - that moved to each institution's project_goals breakdown
     * (see CampaignService::resolveProjectGoals()), shown instead on CampaignInstitutionResource.
     *
     * @return array<string, mixed>
     */
    protected function projectsPayload(bool $withDescription = true): array
    {
        $campaign = $this->campaign();

        if (! $campaign->relationLoaded('projects')) {
            return [];
        }

        return [
            'projects' => $campaign->projects
                ->map(fn (CampaignProject $project): array => [
                    'uuid' => $project->uuid,
                    'name' => $project->name,
                    ...($withDescription ? ['description' => $project->description] : []),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{admin_id: string, name: string}|null
     */
    protected function assigneePayload(): ?array
    {
        $campaign = $this->campaign();

        if (! $campaign->relationLoaded('allocatedAdmin') || $campaign->allocatedAdmin === null) {
            return null;
        }

        return [
            'admin_id' => $campaign->allocatedAdmin->uuid,
            'name' => $campaign->allocatedAdmin->displayName(),
        ];
    }

    /**
     * The goal is the public target, not money actually collected, so it's shown to every
     * viewer; raised_amount (and the progress it implies about real donations) stays gated
     * behind visibility.monetary.
     *
     * @return array<string, string|int>
     */
    protected function amountsPayload(bool $withProgress = false): array
    {
        $campaign = $this->campaign();

        return [
            'goal_amount' => (string) $campaign->goal_amount,
            'goal_amount_formatted' => Money::format($campaign->goal_amount, $campaign->currency),
            ...ViewerVisibility::money([
                'raised_amount' => (string) $campaign->raised_amount,
                'raised_amount_formatted' => Money::format($campaign->raised_amount, $campaign->currency),
            ]),
            ...($withProgress ? ['progress_percentage' => $campaign->progressPercentage()] : []),
        ];
    }

    /**
     * The remittance account, only when loaded and only for viewers who may see institution-level money.
     *
     * @return array<string, array<string, string|null>|null>
     */
    protected function bankAccountFields(): array
    {
        $campaign = $this->campaign();

        if (! $campaign->relationLoaded('bankAccount')) {
            return [];
        }

        $account = $campaign->bankAccount;

        return ViewerVisibility::money(['bank_account' => $account === null ? null : [
            'bank_account_id' => $account->uuid,
            'account_number' => $account->account_number,
            'account_name' => $account->account_name,
            'bank_name' => $account->relationLoaded('bank') ? $account->bank->name : null,
        ]]);
    }
}
