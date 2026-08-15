<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Tax;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Tax\StoreTaxRequest;
use App\Http\Requests\Tenant\Tax\StoreTaxRuleRequest;
use App\Http\Requests\Tenant\Tax\StoreTaxZoneRequest;
use App\Http\Requests\Tenant\Tax\UpdateTaxRequest;
use App\Http\Requests\Tenant\Tax\UpdateTaxZoneRequest;
use App\Http\Resources\Tenant\Tax\TaxResource;
use App\Http\Resources\Tenant\Tax\TaxRuleResource;
use App\Http\Resources\Tenant\Tax\TaxZoneResource;
use App\Models\Tenant\Tax;
use App\Models\Tenant\TaxRule;
use App\Models\Tenant\TaxZone;
use App\Services\Tenant\Tax\TaxAdminService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin tax configuration CRUD.
 */
class TaxController extends Controller
{
    public function __construct(private readonly TaxAdminService $taxAdmin) {}

    #[Response(status: 200, description: 'Paginated taxes.', type: 'array{success: true, message: string, data: TaxResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tax::class);

        $taxes = $this->taxAdmin->listTaxes($request->only(['search', 'is_active', 'per_page']));

        return $this->success(
            TaxResource::collection($taxes->items()),
            'Taxes retrieved successfully.',
            $this->paginationMeta($taxes),
        );
    }

    #[Response(status: 201, description: 'Created tax.', type: 'array{success: true, message: string, data: TaxResource, meta: null, errors: null}')]
    public function store(StoreTaxRequest $request): JsonResponse
    {
        $this->authorize('create', Tax::class);

        return $this->created(
            new TaxResource($this->taxAdmin->storeTax($request->validated())),
            'Tax created successfully.',
        );
    }

    #[Response(status: 200, description: 'A tax.', type: 'array{success: true, message: string, data: TaxResource, meta: null, errors: null}')]
    public function show(Tax $tax): JsonResponse
    {
        $this->authorize('view', $tax);

        return $this->success(
            new TaxResource($this->taxAdmin->showTax($tax)),
            'Tax retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated tax.', type: 'array{success: true, message: string, data: TaxResource, meta: null, errors: null}')]
    public function update(UpdateTaxRequest $request, Tax $tax): JsonResponse
    {
        $this->authorize('update', $tax);

        return $this->updated(
            new TaxResource($this->taxAdmin->updateTax($tax, $request->validated())),
            'Tax updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted tax.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Tax $tax): JsonResponse
    {
        $this->authorize('delete', $tax);
        $this->taxAdmin->destroyTax($tax);

        return $this->deleted('Tax deleted successfully.');
    }

    #[Response(status: 200, description: 'Paginated tax zones.', type: 'array{success: true, message: string, data: TaxZoneResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function indexZones(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TaxZone::class);

        $zones = $this->taxAdmin->listZones($request->only(['is_active', 'per_page']));

        return $this->success(
            TaxZoneResource::collection($zones->items()),
            'Tax zones retrieved successfully.',
            $this->paginationMeta($zones),
        );
    }

    #[Response(status: 201, description: 'Created tax zone.', type: 'array{success: true, message: string, data: TaxZoneResource, meta: null, errors: null}')]
    public function storeZone(StoreTaxZoneRequest $request): JsonResponse
    {
        $this->authorize('create', TaxZone::class);

        return $this->created(
            new TaxZoneResource($this->taxAdmin->storeZone($request->validated())),
            'Tax zone created successfully.',
        );
    }

    #[Response(status: 200, description: 'A tax zone.', type: 'array{success: true, message: string, data: TaxZoneResource, meta: null, errors: null}')]
    public function showZone(TaxZone $taxZone): JsonResponse
    {
        $this->authorize('view', $taxZone);

        return $this->success(
            new TaxZoneResource($this->taxAdmin->showZone($taxZone)),
            'Tax zone retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated tax zone.', type: 'array{success: true, message: string, data: TaxZoneResource, meta: null, errors: null}')]
    public function updateZone(UpdateTaxZoneRequest $request, TaxZone $taxZone): JsonResponse
    {
        $this->authorize('update', $taxZone);

        return $this->updated(
            new TaxZoneResource($this->taxAdmin->updateZone($taxZone, $request->validated())),
            'Tax zone updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted tax zone.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroyZone(TaxZone $taxZone): JsonResponse
    {
        $this->authorize('delete', $taxZone);
        $this->taxAdmin->destroyZone($taxZone);

        return $this->deleted('Tax zone deleted successfully.');
    }

    #[Response(status: 201, description: 'Created tax rule.', type: 'array{success: true, message: string, data: TaxRuleResource, meta: null, errors: null}')]
    public function storeRule(StoreTaxRuleRequest $request): JsonResponse
    {
        $this->authorize('create', Tax::class);

        return $this->created(
            new TaxRuleResource($this->taxAdmin->storeRule($request->validated())),
            'Tax rule created successfully.',
        );
    }

    #[Response(status: 200, description: 'Deleted tax rule.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroyRule(TaxRule $taxRule): JsonResponse
    {
        $this->authorize('delete', Tax::class);
        $this->taxAdmin->destroyRule($taxRule);

        return $this->deleted('Tax rule deleted successfully.');
    }
}
