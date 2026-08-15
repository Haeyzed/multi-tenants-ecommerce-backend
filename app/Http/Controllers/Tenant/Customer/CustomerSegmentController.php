<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\IndexCustomerSegmentRequest;
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
    public function __construct(private readonly CustomerSegmentationService $segmentation) {}

    /**
     * List configured customer segments with their current membership counts.
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
     * List the customers currently matching a segment's rules.
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
