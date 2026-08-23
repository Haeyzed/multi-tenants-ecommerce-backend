<?php

declare(strict_types=1);

namespace App\Jobs\Tenancy;

use App\Enums\Landlord\TenantStatus;
use App\Events\TenantProvisioned;
use App\Events\UserCreated;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Completes tenant provisioning after Stancl CreateDatabase → Migrate → Seed.
 */
class FinalizeTenantProvision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Tenant $tenant) {}

    public function handle(): void
    {
        $seconds = (int) config('tenancy.provisioning.max_execution_time', 300);
        if ($seconds <= 0) {
            set_time_limit(0);
        } else {
            set_time_limit($seconds);
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($this->tenant->getTenantKey());

        /** @var array<string, mixed>|null $pending */
        $pending = $tenant->pending_provision;

        if (! is_array($pending) || ! isset($pending['admin']) || ! is_array($pending['admin'])) {
            Log::warning('FinalizeTenantProvision: missing pending_provision.admin', [
                'tenant_id' => $tenant->getTenantKey(),
            ]);

            return;
        }

        /** @var array{first_name: string, last_name: string, email: string, phone?: string|null, password: string} $admin */
        $admin = $pending['admin'];
        $intendedStatus = $pending['intended_status'] ?? TenantStatus::Active->value;

        try {
            $tenant->run(function () use ($admin): void {
                $user = TenantUser::query()->create([
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name'],
                    'email' => $admin['email'],
                    'phone' => $admin['phone'] ?? null,
                    'password' => $admin['password'],
                ]);

                $user->assignRole('admin');

                event(new UserCreated($user));
            });

            $tenant->forceFill([
                'status' => $intendedStatus,
                'pending_provision' => null,
                'provision_error' => null,
            ])->save();

            event(new TenantProvisioned($tenant->fresh(['domains', 'profile']) ?? $tenant));
        } catch (Throwable $exception) {
            $this->markFailed($tenant, $exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenant->getTenantKey());

        if ($tenant === null) {
            return;
        }

        $this->markFailed($tenant, $exception);
    }

    private function markFailed(Tenant $tenant, ?Throwable $exception): void
    {
        $tenant->forceFill([
            'status' => TenantStatus::Inactive,
            'is_active' => false,
            'pending_provision' => null,
            'provision_error' => $exception?->getMessage() ?? 'Tenant provisioning failed.',
        ])->save();

        Log::error('FinalizeTenantProvision failed', [
            'tenant_id' => $tenant->getTenantKey(),
            'message' => $exception?->getMessage(),
        ]);
    }
}
