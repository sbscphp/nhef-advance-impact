<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
