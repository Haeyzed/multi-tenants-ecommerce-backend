<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Notification\UpdatePreferenceRequest;
use App\Models\Tenant\User;
use App\Services\Notification\NotificationPreferenceService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PreferenceController extends Controller
{
    public function __construct(private readonly NotificationPreferenceService $preferences) {}

    #[Response(
        status: 200,
        description: 'Notification preferences for the authenticated user.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function index(): JsonResponse
    {
        return $this->success(
            $this->preferences->listForUser($this->actor()),
            'Notification preferences retrieved successfully.',
        );
    }

    #[Response(
        status: 200,
        description: 'Updated notification preferences.',
        type: 'array{success: true, message: string, data: array, meta: null, errors: null}',
    )]
    public function update(UpdatePreferenceRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->updated(
            $this->preferences->syncForUser($this->actor(), $data['preferences']),
            'Notification preferences updated successfully.',
        );
    }

    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $user;
    }
}
