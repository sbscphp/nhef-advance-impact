<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkingMessageReaction extends Model
{
    use BelongsToTenant;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('networking_message_reactions.message_id', NetworkingMessage::query()->select('networking_messages.id'));
    }

    protected $guarded = ['id'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(NetworkingMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
