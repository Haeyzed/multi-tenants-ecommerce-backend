<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Enums\Media\MediaCollection;
use App\Models\HR\Candidate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Candidate
 */
class CandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Candidate $candidate */
        $candidate = $this->resource;

        return [
            'id' => $candidate->id,
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'address' => $candidate->address,
            'portfolio_url' => $candidate->portfolio_url,
            'linkedin_url' => $candidate->linkedin_url,
            'notes' => $candidate->notes,
            'status' => $candidate->status,
            'employee_id' => $candidate->employee_id,
            'has_resume' => $candidate->hasMedia(MediaCollection::Resume->value),
            'applications_count' => $candidate->applications_count ?? null,
            'created_at' => $candidate->created_at,
            'updated_at' => $candidate->updated_at,
        ];
    }
}
