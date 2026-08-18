<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\InterviewStatus;
use App\Enums\Tenant\HR\InterviewType;
use Database\Factories\Tenant\InterviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property string|null $timezone
 * @property InterviewStatus $status
 * @property string|null $notes
 * @property array<int, int>|null $reminders_sent
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
        'timezone',
        'status',
        'notes',
        'reminders_sent',
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
            'reminders_sent' => 'array',
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

    /**
     * @return HasMany<InterviewMeeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(InterviewMeeting::class);
    }

    /**
     * @return HasOne<InterviewMeeting, $this>
     */
    public function currentMeeting(): HasOne
    {
        return $this->hasOne(InterviewMeeting::class)->where('is_current', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function recruitmentNotificationPayload(bool $includeHostUrl = false): array
    {
        $this->loadMissing(['application.candidate', 'application.jobOpening', 'currentMeeting']);
        $application = $this->application;
        $timezone = $this->timezone ?: (string) config('app.timezone', 'UTC');
        $scheduled = $this->scheduled_at?->copy()->timezone($timezone);
        $meeting = $this->currentMeeting;

        $payload = [
            'job_title' => $application?->jobOpening?->title ?? '',
            'candidate_name' => trim(($application?->first_name ?? '').' '.($application?->last_name ?? '')),
            'scheduled_at' => $scheduled?->toDateTimeString() ?? $this->scheduled_at?->toDateTimeString(),
            'timezone' => $timezone,
            'meeting_provider' => $meeting?->provider?->value,
            'meeting_join_url' => $meeting?->join_url ?? $this->meeting_url,
        ];

        if ($includeHostUrl) {
            $payload['meeting_host_url'] = $meeting?->host_url;
        }

        return $payload;
    }

    /**
     * @param  Builder<Interview>  $query
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     status?: string|null,
     *     job_application_id?: int|null,
     *     job_opening_id?: int|null,
     *     interviewer_id?: int|null
     * }  $params
     * @return Builder<Interview>
     */
    public function scopeFilter(Builder $query, array $params = []): Builder
    {
        return $query
            ->when($params['status'] ?? null, function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($params['job_application_id'] ?? null, function (Builder $query, int $id): void {
                $query->where('job_application_id', $id);
            })
            ->when($params['job_opening_id'] ?? null, function (Builder $query, int $id): void {
                $query->whereHas('application', fn (Builder $application) => $application->where('job_opening_id', $id));
            })
            ->when($params['interviewer_id'] ?? null, function (Builder $query, int $id): void {
                $query->whereHas('interviewers', fn (Builder $interviewers) => $interviewers->whereKey($id));
            })
            ->when($params['from'] ?? null, function (Builder $query, string $from): void {
                $query->whereDate('scheduled_at', '>=', $from);
            })
            ->when($params['to'] ?? null, function (Builder $query, string $to): void {
                $query->whereDate('scheduled_at', '<=', $to);
            });
    }

    /**
     * @param  Builder<Interview>  $query
     * @return Builder<Interview>
     */
    public function scopeApplySort(Builder $query, ?string $sort = null): Builder
    {
        $allowed = ['scheduled_at', 'status', 'created_at', 'id'];
        $sort = $sort ?: 'scheduled_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            $column = 'scheduled_at';
            $direction = 'asc';
        }

        return $query->orderBy($column, $direction)->orderBy('id');
    }
}
