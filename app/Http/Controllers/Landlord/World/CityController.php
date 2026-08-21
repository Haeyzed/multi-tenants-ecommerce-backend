<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\IndexCityRequest;
use App\Http\Resources\Landlord\World\CityResource;
use App\Services\Landlord\World\CityService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord API controller for cities.
 */
class CityController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  CityService  $cityService
     */
    public function __construct(private readonly CityService $cityService) {}

    /**
     * List cities with pagination, search, and filters.
     *
     * @param  IndexCityRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of cities.',
        type: 'array{success: true, message: string, data: CityResource[], meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, errors: null}',
    )]
    public function index(IndexCityRequest $request): JsonResponse
    {
        $cities = $this->cityService->list($request->validated());

        return $this->success(
            CityResource::collection($cities->items()),
            'Cities retrieved successfully.',
            $this->paginationMeta($cities),
        );
    }

    /**
     * Return city options as label/value pairs.
     *
     * @param  IndexCityRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'City options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexCityRequest $request): JsonResponse
    {
        return $this->success(
            $this->cityService->options($request->validated())->all(),
            'City options retrieved successfully.',
        );
    }

    /**
     * Show a single city by identifier.
     *
     * @param  int  $city
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single city.',
        type: 'array{success: true, message: string, data: CityResource, meta: null, errors: null}',
    )]
    public function show(int $city): JsonResponse
    {
        return $this->success(
            new CityResource($this->cityService->show($city)),
            'City retrieved successfully.',
        );
    }
}
