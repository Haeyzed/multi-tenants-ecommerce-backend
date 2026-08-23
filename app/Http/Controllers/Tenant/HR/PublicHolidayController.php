<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexPublicHolidayRequest;
use App\Http\Requests\Tenant\HR\StorePublicHolidayRequest;
use App\Http\Requests\Tenant\HR\UpdatePublicHolidayRequest;
use App\Http\Resources\Tenant\HR\PublicHolidayResource;
use App\Models\Tenant\HR\PublicHoliday;
use App\Services\Tenant\HR\PublicHolidayService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Public holiday calendar.
 */
#[Group('HR')]
class PublicHolidayController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  PublicHolidayService  $holidays
     */
    public function __construct(private readonly PublicHolidayService $holidays) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexPublicHolidayRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated public holidays.', type: 'array{success: true, message: string, data: PublicHolidayResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexPublicHolidayRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PublicHoliday::class);

        $holidays = $this->holidays->list($request->validated());

        return $this->success(
            PublicHolidayResource::collection($holidays->items()),
            'Public holidays retrieved successfully.',
            $this->paginationMeta($holidays),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StorePublicHolidayRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created public holiday.', type: 'array{success: true, message: string, data: PublicHolidayResource, meta: null, errors: null}')]
    public function store(StorePublicHolidayRequest $request): JsonResponse
    {
        $this->authorize('create', PublicHoliday::class);

        return $this->created(
            new PublicHolidayResource($this->holidays->store($request->validated())),
            'Public holiday created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  PublicHoliday  $public_holiday
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A public holiday.', type: 'array{success: true, message: string, data: PublicHolidayResource, meta: null, errors: null}')]
    public function show(PublicHoliday $public_holiday): JsonResponse
    {
        $this->authorize('view', $public_holiday);

        return $this->success(
            new PublicHolidayResource($this->holidays->show($public_holiday)),
            'Public holiday retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdatePublicHolidayRequest  $request
     * @param  PublicHoliday  $public_holiday
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated public holiday.', type: 'array{success: true, message: string, data: PublicHolidayResource, meta: null, errors: null}')]
    public function update(UpdatePublicHolidayRequest $request, PublicHoliday $public_holiday): JsonResponse
    {
        $this->authorize('update', $public_holiday);

        return $this->updated(
            new PublicHolidayResource($this->holidays->update($public_holiday, $request->validated())),
            'Public holiday updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  PublicHoliday  $public_holiday
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted public holiday.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(PublicHoliday $public_holiday): JsonResponse
    {
        $this->authorize('delete', $public_holiday);
        $this->holidays->destroy($public_holiday);

        return $this->deleted('Public holiday deleted successfully.');
    }
}
