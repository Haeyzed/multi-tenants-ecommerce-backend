<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\Tenant\HR\PerformanceReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PerformanceReview
 */
class PerformanceReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PerformanceReview $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'performance_cycle_id' => $review->performance_cycle_id,
            'employee_id' => $review->employee_id,
            'reviewer_id' => $review->reviewer_id,
            'rating' => $review->rating,
            'summary' => $review->summary,
            'status' => $review->status,
            'submitted_at' => $review->submitted_at,
            'cycle' => $this->whenLoaded('cycle', fn () => $review->cycle === null ? null : [
                'id' => $review->cycle->id,
                'name' => $review->cycle->name,
                'status' => $review->cycle->status,
            ]),
            'employee' => $this->whenLoaded('employee', fn () => $review->employee === null ? null : [
                'id' => $review->employee->id,
                'employee_number' => $review->employee->employee_number,
            ]),
            'created_at' => $review->created_at,
            'updated_at' => $review->updated_at,
        ];
    }
}
