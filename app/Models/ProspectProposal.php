<?php

namespace App\Models;

use App\Enums\ProposalCollaboratorRoleEnum;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectProposal extends Model
{
    use HasFactory, HasUuid;

    protected $guarded = ['id', 'uuid'];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by', 'uuid');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sent_by', 'uuid');
    }

    public function collaborators(): HasMany
    {
        return $this->hasMany(ProposalCollaborator::class, 'proposal_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ProposalRecipient::class, 'proposal_id');
    }

    /** Null when the admin isn't a collaborator at all (not just "viewer" by default). */
    public function collaboratorRole(Admin $admin): ?ProposalCollaboratorRoleEnum
    {
        $collaborator = $this->collaborators->firstWhere('admin_id', $admin->id);

        return $collaborator instanceof ProposalCollaborator
            ? ProposalCollaboratorRoleEnum::from($collaborator->role)
            : null;
    }
}
