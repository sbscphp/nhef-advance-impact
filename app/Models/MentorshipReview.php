<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorshipReview extends Model
{
    use BelongsToTenant, HasUuid;

    public static function constrainToTenant(Builder $query, Institution $tenant): void
    {
        $query->whereIn('mentorship_reviews.mentorship_match_id', MentorshipMatch::query()->select('mentorship_matches.id'));
    }

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
