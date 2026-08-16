<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for a landlord platform settings domain payload.
 *
 * @property-read array{domain: string, settings: array<string, mixed>} $resource
 */
class PlatformSettingsDomainResource extends JsonResource
{
    /**
     * @return array{domain: string, settings: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        /** @var array{domain: string, settings: array<string, mixed>} $payload */
        $payload = $this->resource;

        return [
            'domain' => $payload['domain'],
            'settings' => $payload['settings'],
        ];
    }
}
