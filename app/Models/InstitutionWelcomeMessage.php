<?php

namespace App\Models;

use App\Models\Concerns\OwnedByInstitution;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class InstitutionWelcomeMessage extends Model
{
    use HasUuid, OwnedByInstitution;

    protected $guarded = ['id', 'uuid'];
}
