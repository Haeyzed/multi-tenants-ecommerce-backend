<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Media\MediaCollection;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Render and attach an invoice PDF to media storage.
 */
class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $invoiceId,
        public ?string $tenantId = null,
    ) {}

    /**
     * Generate the invoice PDF inside an isolated tenant context when required.
     */
    public function handle(): void
    {
        $callback = function (): void {
            /** @var Invoice|null $invoice */
            $invoice = Invoice::query()->with(['items', 'order', 'customer'])->find($this->invoiceId);

            if ($invoice === null) {
                return;
            }

            $pdf = Pdf::loadView('invoices.order', [
                'invoice' => $invoice,
                'order' => $invoice->order,
                'customer' => $invoice->customer,
                'items' => $invoice->items,
            ]);

            $filename = $invoice->invoice_number.'.pdf';

            $invoice->addMediaFromString($pdf->output())
                ->usingFileName($filename)
                ->toMediaCollection(MediaCollection::Documents->value);
        };

        if ($this->tenantId === null || $this->tenantId === '') {
            // Queued workers must not touch the central connection. Synchronous
            // handle() (tests / same-request) may run on the current connection.
            if ($this->job !== null) {
                Log::warning('GenerateInvoicePdfJob: tenant id is required', [
                    'invoice_id' => $this->invoiceId,
                ]);

                return;
            }

            $callback();

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('GenerateInvoicePdfJob: tenant not found', ['tenant_id' => $this->tenantId]);

            return;
        }

        $tenant->run($callback);
    }
}
