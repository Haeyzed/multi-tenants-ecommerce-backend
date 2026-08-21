<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\InterviewMeeting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Staff interview meeting resource. Host URLs are staff-only; never send them to candidates.
 *
 * @mixin InterviewMeeting
 */
class InterviewMeetingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var InterviewMeeting $meeting */
        $meeting = $this->resource;

        return [
            'id' => $meeting->id,
            'interview_id' => $meeting->interview_id,
            'provider' => $meeting->provider,
            'external_id' => $meeting->external_id,
            'join_url' => $meeting->join_url,
            'host_url' => $meeting->host_url,
            'password' => $meeting->password,
            'starts_at' => $meeting->starts_at,
            'ends_at' => $meeting->ends_at,
            'status' => $meeting->status,
            'is_current' => $meeting->is_current,
            'failure_reason' => $meeting->failure_reason,
            'created_at' => $meeting->created_at,
            'updated_at' => $meeting->updated_at,
        ];
    }
}
