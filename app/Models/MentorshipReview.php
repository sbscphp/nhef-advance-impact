<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipReview extends Model
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MentorshipMatch::class, 'mentorship_match_id');
    }

    /** Average of the four sub-ratings, rounded to one decimal place. */
    public function averageRating(): float
    {
        return round((
            (int) $this->quality_rating
            + (int) $this->communication_rating
            + (int) $this->responsiveness_rating
            + (int) $this->professionalism_rating
        ) / 4, 1);
    }
}
