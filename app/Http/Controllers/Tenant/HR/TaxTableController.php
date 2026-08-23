<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexTaxTableRequest;
use App\Http\Requests\Tenant\HR\StoreTaxTableRequest;
use App\Http\Requests\Tenant\HR\UpdateTaxTableRequest;
use App\Http\Resources\Tenant\HR\TaxTableResource;
use App\Models\Tenant\HR\TaxTable;
use App\Services\Tenant\HR\TaxTableService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Country PAYE tables.
 */
#[Group('HR')]
class TaxTableController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  TaxTableService  $taxTables
     */
    public function __construct(private readonly TaxTableService $taxTables) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  IndexTaxTableRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated tax tables.', type: 'array{success: true, message: string, data: TaxTableResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexTaxTableRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TaxTable::class);

        $tables = $this->taxTables->list($request->validated());

        return $this->success(
            TaxTableResource::collection($tables->items()),
            'Tax tables retrieved successfully.',
            $this->paginationMeta($tables),
        );
    }

    /**
     * Create a resource.
     *
     * @param  StoreTaxTableRequest  $request
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Created tax table.', type: 'array{success: true, message: string, data: TaxTableResource, meta: null, errors: null}')]
    public function store(StoreTaxTableRequest $request): JsonResponse
    {
        $this->authorize('create', TaxTable::class);

        return $this->created(
            new TaxTableResource($this->taxTables->store($request->validated())),
            'Tax table created successfully.',
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  TaxTable  $tax_table
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'A tax table.', type: 'array{success: true, message: string, data: TaxTableResource, meta: null, errors: null}')]
    public function show(TaxTable $tax_table): JsonResponse
    {
        $this->authorize('view', $tax_table);

        return $this->success(
            new TaxTableResource($this->taxTables->show($tax_table)),
            'Tax table retrieved successfully.',
        );
    }

    /**
     * Update a resource.
     *
     * @param  UpdateTaxTableRequest  $request
     * @param  TaxTable  $tax_table
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Updated tax table.', type: 'array{success: true, message: string, data: TaxTableResource, meta: null, errors: null}')]
    public function update(UpdateTaxTableRequest $request, TaxTable $tax_table): JsonResponse
    {
        $this->authorize('update', $tax_table);

        return $this->updated(
            new TaxTableResource($this->taxTables->update($tax_table, $request->validated())),
            'Tax table updated successfully.',
        );
    }

    /**
     * Delete a resource.
     *
     * @param  TaxTable  $tax_table
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Deleted tax table.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(TaxTable $tax_table): JsonResponse
    {
        $this->authorize('delete', $tax_table);
        $this->taxTables->destroy($tax_table);

        return $this->deleted('Tax table deleted successfully.');
    }
}
