<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkSchedule
 */
class WorkScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkSchedule $schedule */
        $schedule = $this->resource;

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'code' => $schedule->code,
            'is_default' => $schedule->is_default,
            'is_active' => $schedule->is_active,
            'overtime_policy_id' => $schedule->overtime_policy_id,
            'days' => $this->whenLoaded('days', fn () => $schedule->days->map(fn ($day) => [
                'id' => $day->id,
                'weekday' => $day->weekday,
                'start_time' => substr((string) $day->start_time, 0, 5),
                'end_time' => substr((string) $day->end_time, 0, 5),
                'break_minutes' => $day->break_minutes,
                'scheduled_minutes' => $day->scheduledMinutes(),
            ])->values()),
            'overtime_policy' => $this->whenLoaded('overtimePolicy', fn () => $schedule->overtimePolicy === null ? null : [
                'id' => $schedule->overtimePolicy->id,
                'name' => $schedule->overtimePolicy->name,
            ]),
            'created_at' => $schedule->created_at,
            'updated_at' => $schedule->updated_at,
        ];
    }
}
