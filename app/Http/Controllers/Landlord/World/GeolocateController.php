<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\GeolocateRequest;
use App\Http\Resources\Landlord\World\GeolocateResource;
use App\Services\Landlord\World\GeolocateService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Landlord API controller for IP geolocation.
 */
class GeolocateController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  GeolocateService  $geolocateService
     */
    public function __construct(private readonly GeolocateService $geolocateService) {}

    /**
     * Return the current client IP address.
     *
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Detected client IP address.',
        type: 'array{success: true, message: string, data: array{ip: string}, meta: null, errors: null}',
    )]
    public function ip(): JsonResponse
    {
        return $this->success(
            ['ip' => $this->geolocateService->ip()],
            'IP retrieved successfully.',
        );
    }

    /**
     * Geolocate an IP address (or auto-detect the client IP).
     *
     * @param  GeolocateRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Geolocation for the given or detected IP address.',
        type: 'array{success: true, message: string, data: array{ip: string, country: array{id: int, iso2: string, iso3: string|null, name: string, phone_code: string|null, region: string|null, subregion: string|null}|null, state: array{id?: int, name: string|null, state_code: string|null}|null, city: array{id?: int, name: string|null}|null, coordinates: array{latitude: float|null, longitude: float|null, accuracy_radius: int|null}, timezone: array{id?: int, name: string|null}|null, postal_code: string|null}, meta: null, errors: null}',
    )]
    public function index(GeolocateRequest $request): JsonResponse
    {
        try {
            $location = $this->geolocateService->locate($request->validated('ip'));
        } catch (RuntimeException $exception) {
            return $this->notFound($exception->getMessage());
        }

        return $this->success(
            new GeolocateResource($location),
            'Location retrieved successfully.',
        );
    }
}
