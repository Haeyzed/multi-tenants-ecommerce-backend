<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates product badge sync payloads.
 */
class SyncProductBadgesRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'badges' => ['required', 'array'],
            'badges.*.badge_id' => ['required', 'integer', Rule::exists('product_badges', 'id')],
            'badges.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
