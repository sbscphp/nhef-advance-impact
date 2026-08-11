<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NetworkingChannelMember extends Pivot
{
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
