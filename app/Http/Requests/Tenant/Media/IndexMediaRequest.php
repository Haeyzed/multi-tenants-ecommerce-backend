<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Media;

use App\Enums\Media\MediaCollection;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Validates media library index/options query params.
 */
class IndexMediaRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collection' => ['sometimes', 'nullable', 'string', Rule::in([MediaCollection::Library->value])],
            'mime_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
