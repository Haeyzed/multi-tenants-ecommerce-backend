<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollPeriod
 */
class PayrollPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayrollPeriod $period */
        $period = $this->resource;

        return [
            'id' => $period->id,
            'period_start' => $period->period_start?->toDateString(),
            'period_end' => $period->period_end?->toDateString(),
            'payment_date' => $period->payment_date?->toDateString(),
            'frequency' => $period->frequency,
            'status' => $period->status,
            'created_at' => $period->created_at,
            'updated_at' => $period->updated_at,
        ];
    }
}
