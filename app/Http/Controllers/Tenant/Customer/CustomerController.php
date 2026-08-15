<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Enums\Tenant\Customer\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\IndexCustomerRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerStatusRequest;
use App\Http\Resources\Tenant\Customer\CustomerResource;
use App\Models\Tenant\Customer;
use App\Services\Tenant\Customer\CustomerService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Admin customer management endpoints.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    /**
     * List customers with pagination, search, and filters.
     */
    #[Response(
        status: 200,
        description: 'Paginated list of customers.',
        type: 'array{success: true, message: string, data: CustomerResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCustomerRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->customerService->list($request->validated());

        return $this->success(
            CustomerResource::collection($customers->items()),
            'Customers retrieved successfully.',
            $this->paginationMeta($customers),
        );
    }

    /**
     * Show a customer.
     */
    #[Response(
        status: 200,
        description: 'A single customer.',
        type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}',
    )]
    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return $this->success(
            new CustomerResource($this->customerService->show($customer)),
            'Customer retrieved successfully.',
        );
    }

    /**
     * Update a customer (admin — no password changes).
     */
    #[Response(
        status: 200,
        description: 'Updated customer.',
        type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}',
    )]
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->customerService->update($customer, $request->validated());

        return $this->updated(
            new CustomerResource($customer),
            'Customer updated successfully.',
        );
    }

    /**
     * Update a customer's status.
     */
    #[Response(
        status: 200,
        description: 'Updated customer status.',
        type: 'array{success: true, message: string, data: CustomerResource, meta: null, errors: null}',
    )]
    public function updateStatus(UpdateCustomerStatusRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $status = CustomerStatus::from($request->validated('status'));

        $customer = $this->customerService->updateStatus($customer, $status);

        return $this->updated(
            new CustomerResource($customer),
            'Customer status updated successfully.',
        );
    }
}
