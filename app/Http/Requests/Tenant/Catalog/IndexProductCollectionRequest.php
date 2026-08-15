<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Enums\Tenant\Catalog\CollectionStatus;
use App\Enums\Tenant\Catalog\CollectionType;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates collection index query params.
 */
class IndexProductCollectionRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(CollectionStatus::class)],
            'type' => ['sometimes', 'nullable', Rule::enum(CollectionType::class)],
            'sort' => ['sometimes', 'nullable', 'string', 'max:50'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
