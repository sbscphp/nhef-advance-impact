<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalCollaborator extends Model
{
    use BelongsToTenant, HasFactory, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('proposal_collaborators.proposal_id', ProspectProposal::query()->select('prospect_proposals.id'));
    }

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProspectProposal::class, 'proposal_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'invited_by', 'uuid');
    }
}
