<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Landlord\Tenant;
use App\Models\Tenant\DriverLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Delete driver location rows older than the configured retention window.
 */
class CleanupDriverLocationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $tenantId = null,
    ) {}

    /**
     * Purge stale driver locations inside an isolated tenant context when a tenant id is provided.
     */
    public function handle(): void
    {
        $callback = function (): void {
            $days = max(1, (int) config('delivery.location.retention_days', 14));
            $cutoff = now()->subDays($days);

            DriverLocation::query()
                ->where('recorded_at', '<', $cutoff)
                ->orderBy('id')
                ->chunkById(500, function ($locations): void {
                    foreach ($locations as $location) {
                        $location->delete();
                    }
                });
        };

        if ($this->tenantId === null) {
            if ($this->job !== null) {
                Log::warning('CleanupDriverLocationsJob skipped: missing tenant id.');

                return;
            }

            $callback();

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('CleanupDriverLocationsJob skipped: tenant not found.', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $tenant->run($callback);
    }
}
