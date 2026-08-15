<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Storefront;

use App\Http\Requests\BaseRequest;

/**
 * Validation for storefront product recommendation reads.
 */
class ProductRecommendationRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'types' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'session_key' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Requested recommendation types; empty means "every registered provider".
     *
     * @return list<string>
     */
    public function types(): array
    {
        $types = $this->input('types');

        if (! is_string($types) || trim($types) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $types))));
    }

    /**
     * Anonymous storefront session identifier, from the query string or X-Session-Key header.
     */
    public function sessionKey(): ?string
    {
        $sessionKey = $this->input('session_key') ?? $this->header('X-Session-Key');

        if (! is_string($sessionKey)) {
            return null;
        }

        $sessionKey = trim($sessionKey);

        return $sessionKey === '' ? null : mb_substr($sessionKey, 0, 100);
    }

    /**
     * Number of products requested per recommendation type.
     */
    public function limit(): int
    {
        return max(1, min((int) ($this->input('limit') ?? 8), 50));
    }
}
