<?php

namespace App\Models;

use App\Enums\NetworkingChannelTypeEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NetworkingChannel extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'is_locked' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'networking_channel_members', 'channel_id', 'user_id')
            ->using(NetworkingChannelMember::class)
            ->withPivot(['joined_at', 'left_at', 'last_read_at'])
            ->wherePivotNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NetworkingMessage::class, 'channel_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(NetworkingMessage::class, 'channel_id')->latestOfMany();
    }

    public function isDirect(): bool
    {
        return $this->type === NetworkingChannelTypeEnum::DIRECT->value;
    }

    public function isCommunity(): bool
    {
        return $this->type === NetworkingChannelTypeEnum::COMMUNITY->value;
    }

    public function isForum(): bool
    {
        return $this->type === NetworkingChannelTypeEnum::FORUM->value;
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }
}
