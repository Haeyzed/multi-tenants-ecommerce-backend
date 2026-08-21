<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Notification\StoreDeviceTokenRequest;
use App\Http\Resources\Landlord\Notification\DeviceTokenResource;
use App\Models\Landlord\User;
use App\Models\Notification\DeviceToken;
use App\Services\Notification\DeviceTokenService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Landlord device token endpoints.
 */
class DeviceController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  DeviceTokenService  $devices
     */
    public function __construct(private readonly DeviceTokenService $devices) {}

    /**
     * List resources with pagination and filters.
     *
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Registered device tokens.',
        type: 'array{success: true, message: string, data: DeviceTokenResource[], meta: null, errors: null}',
    )]
    public function index(): JsonResponse
    {
        return $this->success(
            DeviceTokenResource::collection($this->devices->listForUser($this->actor())),
            'Device tokens retrieved successfully.',
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreDeviceTokenRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Registered device token.',
        type: 'array{success: true, message: string, data: DeviceTokenResource, meta: null, errors: null}',
    )]
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $token = $this->devices->register($this->actor(), $request->validated());

        return $this->created(
            new DeviceTokenResource($token),
            'Device token registered successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  DeviceToken  $device
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Device token removed.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(DeviceToken $device): JsonResponse
    {
        $this->devices->remove($this->actor(), $device);

        return $this->deleted('Device token removed successfully.');
    }

    /**
     * Resolve the authenticated landlord user.
     *
     * @return User
     */
    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('landlord')->user();

        return $user;
    }
}
