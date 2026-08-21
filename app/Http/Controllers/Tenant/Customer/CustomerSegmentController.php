<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\IndexCustomerSegmentRequest;
use App\Http\Requests\Tenant\Customer\StoreCustomerSegmentRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerSegmentRequest;
use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Http\Resources\Tenant\Customer\CustomerSegmentResource;
use App\Models\Tenant\CustomerSegment;
use App\Services\Tenant\Customer\CustomerSegmentationService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Staff endpoints for rule-based customer segments.
 */
class CustomerSegmentController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  CustomerSegmentationService  $segmentation
     */
    public function __construct(private readonly CustomerSegmentationService $segmentation) {}

    /**
     * List configured customer segments with their current membership counts.
     *
     * @param  IndexCustomerSegmentRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated customer segments.',
        type: 'array{success: true, message: string, data: CustomerSegmentResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCustomerSegmentRequest $request): JsonResponse
    {
        $segments = $this->segmentation->list($request->validated());

        $segments->getCollection()->transform(function (CustomerSegment $segment): CustomerSegment {
            $segment->setAttribute('customers_count', $this->segmentation->count($segment));

            return $segment;
        });

        return $this->success(
            CustomerSegmentResource::collection($segments->items()),
            'Customer segments retrieved successfully.',
            $this->paginationMeta($segments),
        );
    }

    /**
     * Create a customer segment.
     *
     * @param  StoreCustomerSegmentRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created customer segment.',
        type: 'array{success: true, message: string, data: CustomerSegmentResource, meta: null, errors: null}',
    )]
    public function store(StoreCustomerSegmentRequest $request): JsonResponse
    {
        $segment = $this->segmentation->store($request->validated());

        return $this->created(
            new CustomerSegmentResource($this->segmentation->show($segment)),
            'Customer segment created successfully.',
        );
    }

    /**
     * Show a customer segment.
     *
     * @param  CustomerSegment  $segment
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single customer segment.',
        type: 'array{success: true, message: string, data: CustomerSegmentResource, meta: null, errors: null}',
    )]
    public function show(CustomerSegment $segment): JsonResponse
    {
        return $this->success(
            new CustomerSegmentResource($this->segmentation->show($segment)),
            'Customer segment retrieved successfully.',
        );
    }

    /**
     * Update a customer segment.
     *
     * @param  UpdateCustomerSegmentRequest  $request
     * @param  CustomerSegment  $segment
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated customer segment.',
        type: 'array{success: true, message: string, data: CustomerSegmentResource, meta: null, errors: null}',
    )]
    public function update(UpdateCustomerSegmentRequest $request, CustomerSegment $segment): JsonResponse
    {
        $segment = $this->segmentation->update($segment, $request->validated());

        return $this->updated(
            new CustomerSegmentResource($this->segmentation->show($segment)),
            'Customer segment updated successfully.',
        );
    }

    /**
     * Delete a customer segment.
     *
     * @param  CustomerSegment  $segment
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Customer segment deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(CustomerSegment $segment): JsonResponse
    {
        $this->segmentation->destroy($segment);

        return $this->deleted('Customer segment deleted successfully.');
    }

    /**
     * List the customers currently matching a segment's rules.
     *
     * @param  IndexCustomerSegmentRequest  $request
     * @param  string  $slug
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated segment members.',
        type: 'array{success: true, message: string, data: CustomerResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function customers(IndexCustomerSegmentRequest $request, string $slug): JsonResponse
    {
        $segment = $this->segmentation->findBySlug($slug);

        if ($segment === null) {
            throw new NotFoundHttpException('Customer segment not found.');
        }

        $customers = $this->segmentation->customers($segment, $request->validated());

        return $this->success(
            CustomerResource::collection($customers->items()),
            'Segment customers retrieved successfully.',
            $this->paginationMeta($customers) + ['segment' => $segment->slug],
        );
    }
}
