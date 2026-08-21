<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\IndexCountryRequest;
use App\Http\Resources\Landlord\World\CountryResource;
use App\Services\Landlord\World\CountryService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord API controller for countries.
 */
class CountryController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  CountryService  $countryService
     */
    public function __construct(private readonly CountryService $countryService) {}

    /**
     * List countries with pagination, search, and filters.
     *
     * @param  IndexCountryRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of countries.',
        type: 'array{success: true, message: string, data: CountryResource[], meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, errors: null}',
    )]
    public function index(IndexCountryRequest $request): JsonResponse
    {
        $countries = $this->countryService->list($request->validated());

        return $this->success(
            CountryResource::collection($countries->items()),
            'Countries retrieved successfully.',
            $this->paginationMeta($countries),
        );
    }

    /**
     * Return country options as label/value pairs.
     *
     * @param  IndexCountryRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Country options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexCountryRequest $request): JsonResponse
    {
        return $this->success(
            $this->countryService->options($request->validated())->all(),
            'Country options retrieved successfully.',
        );
    }

    /**
     * Show a single country by identifier.
     *
     * @param  int  $country
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single country.',
        type: 'array{success: true, message: string, data: CountryResource, meta: null, errors: null}',
    )]
    public function show(int $country): JsonResponse
    {
        return $this->success(
            new CountryResource($this->countryService->show($country)),
            'Country retrieved successfully.',
        );
    }
}
