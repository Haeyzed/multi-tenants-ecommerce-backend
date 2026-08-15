<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Catalog;

use App\Http\Requests\BaseRequest;

/**
 * Validates SEO upsert payloads.
 */
class UpsertSeoRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta_keywords' => ['sometimes', 'nullable', 'string', 'max:500'],
            'canonical_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'og_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'og_description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
