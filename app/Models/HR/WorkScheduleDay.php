<?php

declare(strict_types=1);

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekday in a work schedule.
 *
 * @property int $id
 * @property int $work_schedule_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 * @property int $break_minutes
 */
class WorkScheduleDay extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_schedule_id',
        'weekday',
        'start_time',
        'end_time',
        'break_minutes',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'break_minutes' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_schedule_id' => 'integer',
            'weekday' => 'integer',
            'break_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function scheduledMinutes(): int
    {
        $start = $this->minutesFromMidnight((string) $this->start_time);
        $end = $this->minutesFromMidnight((string) $this->end_time);

        if ($end <= $start) {
            $end += 24 * 60;
        }

        return max(0, $end - $start - $this->break_minutes);
    }

    protected function minutesFromMidnight(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }
}
