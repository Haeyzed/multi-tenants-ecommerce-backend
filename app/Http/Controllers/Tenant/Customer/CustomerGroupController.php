<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\IndexCustomerGroupRequest;
use App\Http\Requests\Tenant\Customer\StoreCustomerGroupRequest;
use App\Http\Requests\Tenant\Customer\UpdateCustomerGroupRequest;
use App\Http\Resources\Tenant\Customer\CustomerGroupResource;
use App\Models\Tenant\CustomerGroup;
use App\Services\Tenant\Customer\CustomerGroupService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Tenant customer group classification endpoints.
 */
class CustomerGroupController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  CustomerGroupService  $customerGroupService
     */
    public function __construct(private readonly CustomerGroupService $customerGroupService) {}

    /**
     * List customer groups with pagination, search, and filters.
     *
     * @param  IndexCustomerGroupRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Paginated list of customer groups.',
        type: 'array{success: true, message: string, data: CustomerGroupResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}',
    )]
    public function index(IndexCustomerGroupRequest $request): JsonResponse
    {
        $groups = $this->customerGroupService->list($request->validated());

        return $this->success(
            CustomerGroupResource::collection($groups->items()),
            'Customer groups retrieved successfully.',
            $this->paginationMeta($groups),
        );
    }

    /**
     * Customer group options for select inputs.
     *
     * @param  IndexCustomerGroupRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Customer group options.',
        type: ApiResponseSchema::OPTIONS,
    )]
    public function options(IndexCustomerGroupRequest $request): JsonResponse
    {
        return $this->success(
            $this->customerGroupService->options($request->validated()),
            'Customer group options retrieved successfully.',
        );
    }

    /**
     * Create a customer group.
     *
     * @param  StoreCustomerGroupRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 201,
        description: 'Created customer group.',
        type: 'array{success: true, message: string, data: CustomerGroupResource, meta: null, errors: null}',
    )]
    public function store(StoreCustomerGroupRequest $request): JsonResponse
    {
        $group = $this->customerGroupService->store($request->validated());

        return $this->created(
            new CustomerGroupResource($group),
            'Customer group created successfully.',
        );
    }

    /**
     * Show a customer group.
     *
     * @param  CustomerGroup  $customerGroup
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'A single customer group.',
        type: 'array{success: true, message: string, data: CustomerGroupResource, meta: null, errors: null}',
    )]
    public function show(CustomerGroup $customerGroup): JsonResponse
    {
        return $this->success(
            new CustomerGroupResource($this->customerGroupService->show($customerGroup)),
            'Customer group retrieved successfully.',
        );
    }

    /**
     * Update a customer group.
     *
     * @param  UpdateCustomerGroupRequest  $request
     * @param  CustomerGroup  $customerGroup
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Updated customer group.',
        type: 'array{success: true, message: string, data: CustomerGroupResource, meta: null, errors: null}',
    )]
    public function update(UpdateCustomerGroupRequest $request, CustomerGroup $customerGroup): JsonResponse
    {
        $group = $this->customerGroupService->update($customerGroup, $request->validated());

        return $this->updated(
            new CustomerGroupResource($group),
            'Customer group updated successfully.',
        );
    }

    /**
     * Delete a customer group.
     *
     * @param  CustomerGroup  $customerGroup
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Customer group deleted.',
        type: 'array{success: true, message: string, data: null, meta: null, errors: null}',
    )]
    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        $this->customerGroupService->destroy($customerGroup);

        return $this->deleted('Customer group deleted successfully.');
    }
}
