<?php

declare(strict_types=1);

namespace App\Services\Landlord\World;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Nnjeim\World\Actions\Geolocate\IndexAction;
use Nnjeim\World\Geolocate\GeolocateService as WorldGeolocateService;
use RuntimeException;
use Throwable;

/**
 * Application service for landlord IP geolocation via the Nnjeim World package.
 */
class GeolocateService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private readonly IndexAction $indexAction,
        private readonly WorldGeolocateService $worldGeolocate,
    ) {}

    /**
     * Resolve the current client IP from the request.
     *
     * When the request originates from a private/local address (common on Herd/localhost),
     * fall back to an external lookup for the machine's public IP.
     */
    public function ip(): string
    {
        $ip = $this->worldGeolocate->resolveClientIp();

        if (! $this->worldGeolocate->isPrivateIp($ip)) {
            return $ip;
        }

        return $this->resolvePublicIp() ?? $ip;
    }

    /**
     * Geolocate an IP address, or auto-detect the client IP when omitted.
     *
     * @return Collection<string, mixed>
     *
     * @throws RuntimeException
     */
    public function locate(?string $ip = null): Collection
    {
        $action = $this->indexAction->execute([
            'ip' => $ip ?? $this->ip(),
        ]);

        if (! $action->success) {
            $message = $action->withResponse()->message
                ?: 'Unable to geolocate the given IP address.';

            throw new RuntimeException($message);
        }

        return $action->data;
    }

    /**
     * Look up the public outbound IP, trying ipify then ip-api.com.
     */
    protected function resolvePublicIp(): ?string
    {
        $ip = $this->fetchPublicIpFromIpify()
            ?? $this->fetchPublicIpFromIpApi();

        if ($ip === null || ! $this->worldGeolocate->isValidIp($ip)) {
            return null;
        }

        return $ip;
    }

    /**
     * Fetch the public IP from ipify.
     */
    protected function fetchPublicIpFromIpify(): ?string
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->acceptJson()
                ->get('https://api.ipify.org', [
                    'format' => 'json',
                ]);
        } catch (ConnectionException|Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $ip = $response->json('ip');

        return is_string($ip) ? $ip : null;
    }

    /**
     * Fetch the public IP from ip-api.com (same provider the World package uses).
     */
    protected function fetchPublicIpFromIpApi(): ?string
    {
        try {
            $response = Http::timeout(5)
                ->connectTimeout(3)
                ->get('http://ip-api.com/json/', [
                    'fields' => 'query',
                ]);
        } catch (ConnectionException|Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $ip = $response->json('query');

        return is_string($ip) ? $ip : null;
    }
}
