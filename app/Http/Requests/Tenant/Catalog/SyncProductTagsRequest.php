<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates product tag sync payloads.
 */
class SyncProductTagsRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('product_tags', 'id')],
        ];
    }
}
