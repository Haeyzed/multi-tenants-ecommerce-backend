<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\InterviewRecommendation;
use Database\Factories\Tenant\InterviewFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private interviewer scorecard. Never exposed on public APIs.
 *
 * @property int $id
 * @property int $interview_id
 * @property int $user_id
 * @property int|null $rating
 * @property string|null $strengths
 * @property string|null $weaknesses
 * @property InterviewRecommendation|null $recommendation
 * @property string|null $comments
 */
class InterviewFeedback extends Model
{
    /** @use HasFactory<InterviewFeedbackFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'interview_feedback';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'user_id',
        'rating',
        'strengths',
        'weaknesses',
        'recommendation',
        'comments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_id' => 'integer',
            'user_id' => 'integer',
            'rating' => 'integer',
            'recommendation' => InterviewRecommendation::class,
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
