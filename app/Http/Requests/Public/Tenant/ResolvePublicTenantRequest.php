<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Tenant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate public tenant domain resolution input.
 */
class ResolvePublicTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Resolve the hostname to look up (query param or request host).
     */
    public function resolvedDomain(): string
    {
        $explicit = $this->string('domain')->toString();

        if ($explicit !== '') {
            return $explicit;
        }

        $forwarded = $this->header('X-Forwarded-Host');

        if (is_string($forwarded) && $forwarded !== '') {
            return explode(',', $forwarded)[0];
        }

        return (string) $this->getHost();
    }
}
