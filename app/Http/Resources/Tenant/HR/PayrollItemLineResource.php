<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant\HR;

use App\Models\HR\PayrollItemLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PayrollItemLine
 */
class PayrollItemLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PayrollItemLine $line */
        $line = $this->resource;

        return [
            'id' => $line->id,
            'type' => $line->type,
            'code' => $line->code,
            'label' => $line->label,
            'amount' => $line->amount,
        ];
    }
}
