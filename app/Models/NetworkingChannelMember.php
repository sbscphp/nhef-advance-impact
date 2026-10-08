<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

class NetworkingChannelMember extends Pivot
{
    use BelongsToTenant;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('networking_channel_members.channel_id', NetworkingChannel::query()->select('networking_channels.id'));
    }

    public $incrementing = true;

    protected $table = 'networking_channel_members';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_read_at' => 'datetime',
        ];
    }
}
