<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\InterviewMeetingStatus;
use App\Enums\Tenant\HR\MeetingProvider;
use Database\Factories\Tenant\InterviewMeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Provider-backed meeting attached to an interview. Interview.meeting_url is a join URL snapshot.
 *
 * @property int $id
 * @property int $interview_id
 * @property MeetingProvider $provider
 * @property string|null $external_id
 * @property string|null $join_url
 * @property string|null $host_url
 * @property string|null $password
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property InterviewMeetingStatus $status
 * @property bool $is_current
 * @property string|null $failure_reason
 */
class InterviewMeeting extends Model
{
    /** @use HasFactory<InterviewMeetingFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'interview_id',
        'provider',
        'external_id',
        'join_url',
        'host_url',
        'password',
        'starts_at',
        'ends_at',
        'status',
        'is_current',
        'failure_reason',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'created',
        'is_current' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interview_id' => 'integer',
            'provider' => MeetingProvider::class,
            'password' => 'encrypted',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => InterviewMeetingStatus::class,
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}
