<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Media\MediaCollection;
use App\Models\Tenant\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

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

    public function handle(): void
    {
        if ($this->tenantId !== null) {
            /** @var TenantWithDatabase|null $tenant */
            $tenant = tenancy()->find($this->tenantId);
            if ($tenant !== null) {
                tenancy()->initialize($tenant);
            }
        }

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
    }
}
