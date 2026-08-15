<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\World;

use App\Models\Landlord\World\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API resource for Currency world data.
 *
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
{
    /**
     * Transform the currency resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'country_id' => data_get($this->resource, 'country_id'),
            'name' => data_get($this->resource, 'name'),
            'code' => data_get($this->resource, 'code'),
            'precision' => data_get($this->resource, 'precision'),
            'symbol' => data_get($this->resource, 'symbol'),
            'symbol_native' => data_get($this->resource, 'symbol_native'),
            'symbol_first' => data_get($this->resource, 'symbol_first'),
            'decimal_mark' => data_get($this->resource, 'decimal_mark'),
            'thousands_separator' => data_get($this->resource, 'thousands_separator'),
        ];
    }
}
