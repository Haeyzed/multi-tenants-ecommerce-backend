<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\Tenant\Commerce\RefundStatus;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Refund;
use App\Services\Tenant\Commerce\RefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconcile Processing refunds against the payment provider for one tenant.
 */
class ReconcileProcessingRefundsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $tenantId = null,
    ) {}

    public function handle(RefundService $refunds): void
    {
        $callback = function () use ($refunds): void {
            Refund::query()
                ->with(['order', 'payment'])
                ->where('status', RefundStatus::Processing)
                ->whereNotNull('order_payment_id')
                ->orderBy('id')
                ->chunkById(50, function ($chunk) use ($refunds): void {
                    foreach ($chunk as $refund) {
                        try {
                            $refunds->reconcile($refund);
                        } catch (Throwable $exception) {
                            Log::warning('Failed to reconcile processing refund', [
                                'refund_id' => $refund->id,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
                    }
                });
        };

        if ($this->tenantId === null || $this->tenantId === '') {
            if ($this->job !== null) {
                Log::warning('ReconcileProcessingRefundsJob: tenant id is required');

                return;
            }

            $callback();

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('ReconcileProcessingRefundsJob: tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $tenant->run($callback);
    }
}
