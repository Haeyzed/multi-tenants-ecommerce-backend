<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\UpdateInterviewMeetingProviderSettingRequest;
use App\Services\Tenant\HR\Meetings\InterviewMeetingProviderSettingService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant InterviewMeetingProviderController endpoints.
 */
#[Group('HR / Recruitment / Meeting Providers')]
class InterviewMeetingProviderController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  InterviewMeetingProviderSettingService  $providers
     */
    public function __construct(private readonly InterviewMeetingProviderSettingService $providers) {}

    /**
     * List resources with pagination and filters.
     *
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Meeting providers with configuration metadata. Secrets are never returned.', type: 'array{success: true, message: string, data: array<int, array{provider: string, enabled: bool, configured: bool, always_available: bool, capabilities: array<string, mixed>, configured_fields: list<string>}>, meta: null, errors: null}')]
    public function index(): JsonResponse
    {
        $this->authorize('viewHrSettings');

        return $this->success(
            $this->providers->list(),
            'Interview meeting providers retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateInterviewMeetingProviderSettingRequest  $request
     * @param  string  $provider
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated meeting provider configuration. Secrets are never returned.', type: 'array{success: true, message: string, data: array{provider: string, enabled: bool, configured: bool, always_available: bool, capabilities: array<string, mixed>, configured_fields: list<string>}, meta: null, errors: null}')]
    public function update(UpdateInterviewMeetingProviderSettingRequest $request, string $provider): JsonResponse
    {
        $this->authorize('updateHrSettings');

        return $this->updated(
            $this->providers->upsert($provider, $request->validated()),
            'Interview meeting provider updated successfully.',
        );
    }

    /**
     * Test.
     *
     * @param  string  $provider
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Provider credential test result. Secrets are never returned.', type: 'array{success: true, message: string, data: array{provider: string, ok: bool, configured: bool}, meta: null, errors: null}')]
    public function test(string $provider): JsonResponse
    {
        $this->authorize('updateHrSettings');

        return $this->success(
            $this->providers->test($provider),
            'Meeting provider connection succeeded.',
        );
    }
}
