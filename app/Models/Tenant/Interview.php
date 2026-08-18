<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\InterviewType;
use Database\Factories\Tenant\InterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Interview scheduled against an application.
 *
 * @property int $id
 * @property int $job_application_id
 * @property InterviewType $interview_type
 * @property Carbon $scheduled_at
 * @property int|null $duration_minutes
 * @property string|null $location
 * @property string|null $meeting_url
 * @property InterviewStatus $status
 * @property string|null $notes
 */
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_application_id',
        'interview_type',
        'scheduled_at',
        'duration_minutes',
        'location',
        'meeting_url',
        'status',
        'notes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'interview_type' => 'other',
        'status' => 'scheduled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'job_application_id' => 'integer',
            'interview_type' => InterviewType::class,
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => InterviewStatus::class,
        ];
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interview_interviewers')->withTimestamps();
    }

    /**
     * @return HasMany<InterviewFeedback, $this>
     */
    public function feedback(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class);
    }
}
