<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\Notification\DeviceType;
use App\Models\Notification\DeviceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Manages push device token registrations for a user.
 */
class DeviceTokenService
{
    /**
     * @return Collection<int, DeviceToken>
     */
    public function listForUser(Model $user): Collection
    {
        return DeviceToken::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array{device_type: string, device_token: string, provider?: string|null, app_version?: string|null}  $data
     */
    public function register(Model $user, array $data): DeviceToken
    {
        /** @var DeviceToken $token */
        $token = DeviceToken::query()->updateOrCreate(
            ['device_token' => $data['device_token']],
            [
                'user_id' => $user->getKey(),
                'device_type' => DeviceType::from($data['device_type']),
                'provider' => $data['provider'] ?? 'fcm',
                'app_version' => $data['app_version'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );

        return $token;
    }

    public function remove(Model $user, DeviceToken $deviceToken): void
    {
        if ((int) $deviceToken->user_id !== (int) $user->getKey()) {
            abort(404);
        }

        $deviceToken->delete();
    }

    public function hasActiveDevices(Model $user): bool
    {
        return DeviceToken::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->exists();
    }
}
