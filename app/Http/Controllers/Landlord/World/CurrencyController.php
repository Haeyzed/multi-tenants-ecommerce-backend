<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\IndexCurrencyRequest;
use App\Http\Resources\Landlord\World\CurrencyResource;
use App\Services\Landlord\World\CurrencyService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord API controller for currencies.
 */
class CurrencyController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly CurrencyService $currencyService) {}

    /**
     * List currencies with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of currencies.',
        type: 'array{success: true, message: string, data: CurrencyResource[], meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, errors: null}',
    )]
    public function index(IndexCurrencyRequest $request): JsonResponse
    {
        $currencies = $this->currencyService->list($request->validated());

        return $this->success(
            CurrencyResource::collection($currencies->items()),
            'Currencies retrieved successfully.',
            $this->paginationMeta($currencies),
        );
    }

    /**
     * Return currency options as label/value pairs.
     */
    #[Response(
        status: 200,
        description: 'Currency options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexCurrencyRequest $request): JsonResponse
    {
        return $this->success(
            $this->currencyService->options($request->validated())->all(),
            'Currency options retrieved successfully.',
        );
    }

    /**
     * Show a single currency by identifier.
     */
    #[Response(
        status: 200,
        description: 'A single currency.',
        type: 'array{success: true, message: string, data: CurrencyResource, meta: null, errors: null}',
    )]
    public function show(int $currency): JsonResponse
    {
        return $this->success(
            new CurrencyResource($this->currencyService->show($currency)),
            'Currency retrieved successfully.',
        );
    }
}
