<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Settings\UpdateSettingsDomainRequest;
use App\Http\Resources\Tenant\Settings\SettingsDomainResource;
use App\Services\Tenant\Settings\TenantSettingsService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant settings domains backed by commerce_settings KV (+ store profile snapshot).
 */
class SettingsController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  TenantSettingsService  $settings
     */
    public function __construct(private readonly TenantSettingsService $settings) {}

    /**
     * Retrieve settings for a domain.
     *
     * @param  string  $domain
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Settings domain payload.',
        type: 'array{success: true, message: string, data: SettingsDomainResource, meta: null, errors: null}',
    )]
    public function show(string $domain): JsonResponse
    {
        return $this->success(
            new SettingsDomainResource([
                'domain' => $domain,
                'settings' => $this->settings->getDomain($domain),
            ]),
            'Settings retrieved successfully.',
        );
    }

    /**
     * Update settings for a mutable domain.
     *
     * @param  UpdateSettingsDomainRequest  $request
     * @param  string  $domain
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated settings domain payload.',
        type: 'array{success: true, message: string, data: SettingsDomainResource, meta: null, errors: null}',
    )]
    public function update(UpdateSettingsDomainRequest $request, string $domain): JsonResponse
    {
        return $this->updated(
            new SettingsDomainResource([
                'domain' => $domain,
                'settings' => $this->settings->updateDomain($domain, $request->settingsPayload()),
            ]),
            'Settings updated successfully.',
        );
    }
}
