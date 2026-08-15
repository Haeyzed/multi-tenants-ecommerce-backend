<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use App\Models\Landlord\World\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Language world data.
 *
 * @mixin Language
 */
class LanguageResource extends JsonResource
{
    /**
     * Transform the language resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'code' => data_get($this->resource, 'code'),
            'name' => data_get($this->resource, 'name'),
            'name_native' => data_get($this->resource, 'name_native'),
            'dir' => data_get($this->resource, 'dir'),
        ];
    }
}
