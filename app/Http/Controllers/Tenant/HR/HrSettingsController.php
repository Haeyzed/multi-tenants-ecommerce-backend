<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\UpdateHrSettingsRequest;
use App\Http\Resources\Tenant\Settings\SettingsDomainResource;
use App\Services\Tenant\HR\HrSettingsService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant HR settings endpoints.
 */
#[Group('HR')]
class HrSettingsController extends Controller
{
    public function __construct(private readonly HrSettingsService $hrSettings) {}

    #[Response(status: 200, description: 'HR settings payload.', type: 'array{success: true, message: string, data: SettingsDomainResource, meta: null, errors: null}')]
    public function show(): JsonResponse
    {
        $this->authorize('viewHrSettings');

        return $this->success(
            new SettingsDomainResource([
                'domain' => HrSettingsService::DOMAIN,
                'settings' => $this->hrSettings->all(),
            ]),
            'HR settings retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated HR settings payload.', type: 'array{success: true, message: string, data: SettingsDomainResource, meta: null, errors: null}')]
    public function update(UpdateHrSettingsRequest $request): JsonResponse
    {
        $this->authorize('updateHrSettings');

        return $this->updated(
            new SettingsDomainResource([
                'domain' => HrSettingsService::DOMAIN,
                'settings' => $this->hrSettings->update($request->settingsPayload()),
            ]),
            'HR settings updated successfully.',
        );
    }
}
