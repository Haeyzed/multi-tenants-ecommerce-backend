<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Settings\UpdatePlatformSettingsDomainRequest;
use App\Http\Resources\Landlord\Settings\PlatformSettingsDomainResource;
use App\Services\Landlord\Settings\PlatformSettingsService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord platform settings domains (central DB).
 */
class PlatformSettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    /**
     * Retrieve settings for a domain.
     */
    #[Response(
        status: 200,
        description: 'Platform settings domain payload.',
        type: 'array{success: true, message: string, data: PlatformSettingsDomainResource, meta: null, errors: null}',
    )]
    public function show(string $domain): JsonResponse
    {
        return $this->success(
            new PlatformSettingsDomainResource([
                'domain' => $domain,
                'settings' => $this->settings->getDomain($domain),
            ]),
            'Platform settings retrieved successfully.',
        );
    }

    /**
     * Update settings for a domain.
     */
    #[Response(
        status: 200,
        description: 'Updated platform settings domain payload.',
        type: 'array{success: true, message: string, data: PlatformSettingsDomainResource, meta: null, errors: null}',
    )]
    public function update(UpdatePlatformSettingsDomainRequest $request, string $domain): JsonResponse
    {
        return $this->updated(
            new PlatformSettingsDomainResource([
                'domain' => $domain,
                'settings' => $this->settings->updateDomain($domain, $request->settingsPayload()),
            ]),
            'Platform settings updated successfully.',
        );
    }
}
