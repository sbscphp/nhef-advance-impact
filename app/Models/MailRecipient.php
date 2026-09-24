<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailRecipient extends Model
{
    use BelongsToTenant, HasFactory, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('mail_recipients.mail_id', Mail::query()->select('mails.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public function mail(): BelongsTo
    {
        return $this->belongsTo(Mail::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
