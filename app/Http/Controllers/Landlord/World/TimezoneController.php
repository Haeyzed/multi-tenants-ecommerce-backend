<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\IndexTimezoneRequest;
use App\Http\Resources\Landlord\World\TimezoneResource;
use App\Services\Landlord\World\TimezoneService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord API controller for timezones.
 */
class TimezoneController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly TimezoneService $timezoneService) {}

    /**
     * List timezones with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of timezones.',
        type: 'array{success: true, message: string, data: TimezoneResource[], meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, errors: null}',
    )]
    public function index(IndexTimezoneRequest $request): JsonResponse
    {
        $timezones = $this->timezoneService->list($request->validated());

        return $this->success(
            TimezoneResource::collection($timezones->items()),
            'Timezones retrieved successfully.',
            $this->paginationMeta($timezones),
        );
    }

    /**
     * Return timezone options as label/value pairs.
     */
    #[Response(
        status: 200,
        description: 'Timezone options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexTimezoneRequest $request): JsonResponse
    {
        return $this->success(
            $this->timezoneService->options($request->validated())->all(),
            'Timezone options retrieved successfully.',
        );
    }

    /**
     * Show a single timezone by identifier.
     */
    #[Response(
        status: 200,
        description: 'A single timezone.',
        type: 'array{success: true, message: string, data: TimezoneResource, meta: null, errors: null}',
    )]
    public function show(int $timezone): JsonResponse
    {
        return $this->success(
            new TimezoneResource($this->timezoneService->show($timezone)),
            'Timezone retrieved successfully.',
        );
    }
}
