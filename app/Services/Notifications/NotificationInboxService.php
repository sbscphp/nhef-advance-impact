<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategoryEnum;
use App\Http\Requests\Concerns\ListingFilterRules;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;

class NotificationInboxService
{
    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     * @param  array<string, mixed>  $validated
     */
    public function paginate(Model $recipient, int $perPage = 15, int $page = 1, array $validated = []): LengthAwarePaginator
    {
        return $this->listQuery($recipient, $validated)
            ->paginate(perPage: $perPage, page: $page, pageName: 'page');
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     * @param  array<string, mixed>  $validated
     * @return array{unread_count: int, read_count: int}
     */
    public function inboxCounts(Model $recipient, array $validated = []): array
    {
        $query = $this->scopedQuery($recipient, $validated);

        return [
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'read_count' => (clone $query)->whereNotNull('read_at')->count(),
        ];
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     */
    public function findForRecipient(Model $recipient, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $recipient->notifications()->whereKey($id)->firstOrFail();

        return $notification;
    }

    /**
     * @param  Model&object{unreadNotifications: mixed}  $recipient
     */
    public function markAllRead(Model $recipient): void
    {
        $recipient->unreadNotifications->markAsRead();
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     */
    public function markRead(Model $recipient, string $id): DatabaseNotification
    {
        $notification = $this->findForRecipient($recipient, $id);
        $notification->markAsRead();

        return $notification->fresh();
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     */
    public function markUnread(Model $recipient, string $id): DatabaseNotification
    {
        $notification = $this->findForRecipient($recipient, $id);
        $notification->markAsUnread();

        return $notification->fresh();
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     */
    public function dismiss(Model $recipient, string $id): void
    {
        $this->findForRecipient($recipient, $id)->delete();
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     * @param  array<string, mixed>  $validated
     */
    private function listQuery(Model $recipient, array $validated): Builder|Relation
    {
        $query = $this->scopedQuery($recipient, $validated)
            ->with('notifiable')
            ->latest();
        $this->applyReadStatusFilter($query, $validated);
        $this->applyCategoryFilter($query, $validated);

        return $query;
    }

    /**
     * Category counts for the left-hand filter list (System Notification / Core Platform / etc.),
     * always unfiltered by the current read-status/date selection so the sidebar counts stay
     * stable while browsing - only the visible list itself reacts to those filters.
     *
     * @param  Model&object{notifications(): mixed}  $recipient
     * @return list<array{key: string, label: string, count: int}>
     */
    public function categoryCounts(Model $recipient): array
    {
        return array_map(
            fn (NotificationCategoryEnum $category): array => [
                'key' => $category->value,
                'label' => $category->label(),
                'count' => $this->categoryCount($recipient, $category),
            ],
            NotificationCategoryEnum::cases(),
        );
    }

    /**
     * JSON_EXTRACT returns SQL NULL for notifications with no "module" key at all, which
     * whereIn() never matches - "Others" also needs an explicit OR-NULL to catch those, nested
     * in its own group so it can't leak past the recipient scope on notifications() itself.
     *
     * @param  Model&object{notifications(): mixed}  $recipient
     */
    private function categoryCount(Model $recipient, NotificationCategoryEnum $category): int
    {
        $query = $recipient->notifications();

        if ($category === NotificationCategoryEnum::OTHERS) {
            $query->where(function (Builder|Relation $q) use ($category): void {
                $q->whereIn('data->module', $category->modules())->orWhereNull('data->module');
            });
        } else {
            $query->whereIn('data->module', $category->modules());
        }

        return $query->count();
    }

    /**
     * @param  Model&object{notifications(): mixed}  $recipient
     * @param  array<string, mixed>  $validated
     */
    private function scopedQuery(Model $recipient, array $validated): Builder|Relation
    {
        $query = $recipient->notifications();
        ListingFilterRules::applyResolvedDateRange($query, $validated, 'created_at');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyReadStatusFilter(Builder|Relation $query, array $validated): void
    {
        $status = strtolower((string) data_get($validated, 'filters.read_status', 'all'));

        match ($status) {
            'read' => $query->whereNotNull('read_at'),
            'unread' => $query->whereNull('read_at'),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyCategoryFilter(Builder|Relation $query, array $validated): void
    {
        $category = data_get($validated, 'filters.category');

        if (! is_string($category) || $category === '') {
            return;
        }

        $categoryEnum = NotificationCategoryEnum::tryFrom($category);

        if ($categoryEnum === null) {
            return;
        }

        if ($categoryEnum === NotificationCategoryEnum::OTHERS) {
            $query->where(function (Builder|Relation $q) use ($categoryEnum): void {
                $q->whereIn('data->module', $categoryEnum->modules())->orWhereNull('data->module');
            });

            return;
        }

        $query->whereIn('data->module', $categoryEnum->modules());
    }
}
