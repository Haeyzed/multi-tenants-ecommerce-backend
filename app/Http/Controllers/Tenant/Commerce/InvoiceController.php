<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Commerce\InvoiceResource;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\InvoiceService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff invoice management endpoints.
 */
class InvoiceController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  InvoiceService  $invoiceService
     */
    public function __construct(private readonly InvoiceService $invoiceService) {}

    /**
     * List resources with pagination and filters.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Paginated invoices.', type: 'array{success: true, message: string, data: InvoiceResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceService->list($request->only(['order_id', 'customer_id', 'status', 'per_page']));

        return $this->success(
            InvoiceResource::collection($invoices->items()),
            'Invoices retrieved successfully.',
            $this->paginationMeta($invoices),
        );
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Invoice  $invoice
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'An invoice.', type: 'array{success: true, message: string, data: InvoiceResource, meta: null, errors: null}')]
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->success(
            new InvoiceResource($this->invoiceService->show($invoice)),
            'Invoice retrieved successfully.',
        );
    }

    /**
     * Generate for order.
     *
     * @param  Order  $order
     * @return JsonResponse
     */
    #[Response(status: 201, description: 'Generated invoice for order.', type: 'array{success: true, message: string, data: InvoiceResource, meta: null, errors: null}')]
    public function generateForOrder(Order $order): JsonResponse
    {
        $this->authorize('generate', Invoice::class);

        return $this->created(
            new InvoiceResource($this->invoiceService->generateForOrder($order)),
            'Invoice generated successfully.',
        );
    }

    /**
     * Download.
     *
     * @param  Invoice  $invoice
     * @return JsonResponse
     */
    #[Response(status: 200, description: 'Invoice download URL.', type: 'array{success: true, message: string, data: array{invoice: InvoiceResource, download_url: string|null}, meta: null, errors: null}')]
    public function download(Invoice $invoice): JsonResponse
    {
        $this->authorize('download', $invoice);

        $result = $this->invoiceService->download($invoice);

        return $this->success([
            'invoice' => new InvoiceResource($result['invoice']),
            'download_url' => $result['media_url'],
        ], 'Invoice download URL retrieved successfully.');
    }
}
