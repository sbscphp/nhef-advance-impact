<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalRecipient extends Model
{
    use BelongsToTenant, HasFactory, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('proposal_recipients.proposal_id', ProspectProposal::query()->select('prospect_proposals.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProspectProposal::class, 'proposal_id');
    }
}
