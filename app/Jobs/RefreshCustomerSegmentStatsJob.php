<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\CustomerSegment;
use App\Services\Tenant\Customer\CustomerSegmentationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Materialize segment membership (and refresh counts) for all tenant segments.
 */
class RefreshCustomerSegmentStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $tenantId = null,
    ) {}

    /**
     * Materialize membership inside an isolated tenant context when a tenant id is provided.
     */
    public function handle(CustomerSegmentationService $segmentation): void
    {
        $callback = function () use ($segmentation): void {
            CustomerSegment::query()->orderBy('id')->chunkById(50, function ($segments) use ($segmentation): void {
                foreach ($segments as $segment) {
                    /** @var CustomerSegment $segment */
                    $segmentation->materialize($segment);
                }
            });
        };

        if ($this->tenantId === null) {
            if ($this->job !== null) {
                Log::warning('RefreshCustomerSegmentStatsJob skipped: missing tenant id.');

                return;
            }

            $callback();

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('RefreshCustomerSegmentStatsJob skipped: tenant not found.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $tenant->run($callback);
    }
}
