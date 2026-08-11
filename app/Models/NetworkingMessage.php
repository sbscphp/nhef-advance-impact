<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NetworkingMessage extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NetworkingChannel::class, 'channel_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(NetworkingMessageReaction::class, 'message_id');
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_url !== null;
    }
}
