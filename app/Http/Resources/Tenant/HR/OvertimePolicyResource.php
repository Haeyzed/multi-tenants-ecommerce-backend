<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\OvertimePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OvertimePolicy
 */
class OvertimePolicyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OvertimePolicy $policy */
        $policy = $this->resource;

        return [
            'id' => $policy->id,
            'name' => $policy->name,
            'code' => $policy->code,
            'is_default' => $policy->is_default,
            'is_active' => $policy->is_active,
            'weekday_rate_percent' => $policy->weekday_rate_percent,
            'weekend_rate_percent' => $policy->weekend_rate_percent,
            'holiday_rate_percent' => $policy->holiday_rate_percent,
            'daily_threshold_minutes' => $policy->daily_threshold_minutes,
            'max_daily_minutes' => $policy->max_daily_minutes,
            'weekly_threshold_minutes' => $policy->weekly_threshold_minutes,
            'weekly_rate_percent' => $policy->weekly_rate_percent,
            'round_to_minutes' => $policy->round_to_minutes,
            'created_at' => $policy->created_at,
            'updated_at' => $policy->updated_at,
        ];
    }
}
