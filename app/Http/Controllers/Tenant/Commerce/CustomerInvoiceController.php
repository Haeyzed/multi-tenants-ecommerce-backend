<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Commerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Commerce\InvoiceResource;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use App\Services\Tenant\Commerce\InvoiceService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Customer invoice access endpoints.
 */
class CustomerInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    #[Response(status: 200, description: 'Customer order invoice.', type: 'array{success: true, message: string, data: InvoiceResource, meta: null, errors: null}')]
    public function forOrder(Order $order): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new InvoiceResource($this->invoiceService->customerForOrder($customer, $order)),
            'Invoice retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Customer invoice.', type: 'array{success: true, message: string, data: InvoiceResource, meta: null, errors: null}')]
    public function show(Invoice $invoice): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $this->success(
            new InvoiceResource($this->invoiceService->customerShow($customer, $invoice)),
            'Invoice retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Customer invoice download URL.', type: 'array{success: true, message: string, data: array{invoice: InvoiceResource, download_url: string|null}, meta: null, errors: null}')]
    public function download(Invoice $invoice): JsonResponse
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $this->invoiceService->customerShow($customer, $invoice);
        $result = $this->invoiceService->download($invoice);

        return $this->success([
            'invoice' => new InvoiceResource($result['invoice']),
            'download_url' => $result['media_url'],
        ], 'Invoice download URL retrieved successfully.');
    }
}
