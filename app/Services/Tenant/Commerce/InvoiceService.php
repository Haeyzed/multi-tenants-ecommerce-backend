<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Media\MediaCollection;
use App\Enums\Tenant\Commerce\InvoiceStatus;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Generate and retrieve order invoices.
 */
class InvoiceService
{
    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{order_id?: int|null, customer_id?: int|null, status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Invoice::query()->with(['order', 'customer', 'items'])->latest('id');

        if (! empty($params['order_id'])) {
            $query->where('order_id', (int) $params['order_id']);
        }

        if (! empty($params['customer_id'])) {
            $query->where('customer_id', (int) $params['customer_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Generate for order.
     *
     * @param  Order  $order
     * @param  bool  $queuePdf
     * @return Invoice
     */
    public function generateForOrder(Order $order, bool $queuePdf = true): Invoice
    {
        $existing = Invoice::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', InvoiceStatus::Void)
            ->first();

        if ($existing !== null) {
            return $existing->load(['items', 'order', 'customer']);
        }

        return DB::transaction(function () use ($order, $queuePdf): Invoice {
            $order->loadMissing('items', 'customer');

            $invoice = Invoice::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'status' => InvoiceStatus::Issued,
                'currency' => $order->currency,
                'subtotal' => (string) $order->subtotal,
                'discount_total' => (string) $order->discount_total,
                'tax_total' => (string) $order->tax_total,
                'shipping_total' => (string) $order->shipping_total,
                'grand_total' => (string) $order->grand_total,
                'issued_at' => now(),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'tax_snapshot' => $order->tax_snapshot,
                ],
            ]);

            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'order_item_id' => $item->id,
                    'description' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'tax_amount' => (string) $item->tax_amount,
                    'subtotal' => (string) $item->subtotal,
                    'total' => (string) $item->total,
                ]);
            }

            if ($queuePdf) {
                $tenantKey = tenancy()->initialized ? tenancy()->tenant?->getTenantKey() : null;

                if ($tenantKey !== null) {
                    GenerateInvoicePdfJob::dispatch($invoice->id, $tenantKey);
                }
            }

            return $invoice->fresh(['items', 'order', 'customer']) ?? $invoice;
        });
    }

    /**
     * Retrieve a single resource.
     *
     * @param  Invoice  $invoice
     * @return Invoice
     */
    public function show(Invoice $invoice): Invoice
    {
        return $invoice->load(['items', 'order', 'customer']);
    }

    /**
     * Customer show.
     *
     * @param  Customer  $customer
     * @param  Invoice  $invoice
     * @return Invoice
     */
    public function customerShow(Customer $customer, Invoice $invoice): Invoice
    {
        if ($invoice->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('Invoice does not belong to this customer.');
        }

        return $this->show($invoice);
    }

    /**
     * Customer for order.
     *
     * @param  Customer  $customer
     * @param  Order  $order
     * @return Invoice
     */
    public function customerForOrder(Customer $customer, Order $order): Invoice
    {
        if ($order->customer_id !== $customer->id) {
            throw new AccessDeniedHttpException('Order does not belong to this customer.');
        }

        return $this->generateForOrder($order);
    }

    /**
     * Download.
     *
     * @param  Invoice  $invoice
     * @return array{invoice: Invoice, media_url: string|null}
     */
    public function download(Invoice $invoice): array
    {
        $invoice = $this->show($invoice);
        $media = $invoice->getFirstMedia(MediaCollection::Documents->value);

        return [
            'invoice' => $invoice,
            'media_url' => $media?->getUrl(),
        ];
    }

    /**
     * Generate invoice number.
     *
     * @return string
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV-'.now()->format('Y').'-';

        $latest = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
