<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\World\IndexLanguageRequest;
use App\Http\Resources\Landlord\World\LanguageResource;
use App\Services\Landlord\World\LanguageService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Landlord API controller for languages.
 */
class LanguageController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  LanguageService  $languageService
     */
    public function __construct(private readonly LanguageService $languageService) {}

    /**
     * List languages with pagination, search, and filters.
     *
     * @param  IndexLanguageRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of languages.',
        type: 'array{success: true, message: string, data: LanguageResource[], meta: array{current_page: int, last_page: int, per_page: int, total: int, from: int|null, to: int|null}, errors: null}',
    )]
    public function index(IndexLanguageRequest $request): JsonResponse
    {
        $languages = $this->languageService->list($request->validated());

        return $this->success(
            LanguageResource::collection($languages->items()),
            'Languages retrieved successfully.',
            $this->paginationMeta($languages),
        );
    }

    /**
     * Return language options as label/value pairs.
     *
     * @param  IndexLanguageRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Language options for select inputs.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexLanguageRequest $request): JsonResponse
    {
        return $this->success(
            $this->languageService->options($request->validated())->all(),
            'Language options retrieved successfully.',
        );
    }

    /**
     * Show a single language by identifier.
     *
     * @param  int  $language
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single language.',
        type: 'array{success: true, message: string, data: LanguageResource, meta: null, errors: null}',
    )]
    public function show(int $language): JsonResponse
    {
        return $this->success(
            new LanguageResource($this->languageService->show($language)),
            'Language retrieved successfully.',
        );
    }
}
