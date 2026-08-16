<?php

declare(strict_types=1);

namespace App\Tenancy\Bootstrappers;

use App\Models\Tenant\CommerceSetting;
use App\Models\Tenant\TenantPaymentGateway;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Merge per-tenant payment gateway credentials into runtime payment config.
 *
 * Must run after DatabaseTenancyBootstrapper so the tenant connection is active.
 */
class TenantPaymentConfigBootstrapper implements TenancyBootstrapper
{
    /**
     * @var array<string, mixed>
     */
    protected array $originalConfig = [];

    public function __construct(
        protected Repository $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalConfig = [
            'payment.default' => $this->config->get('payment.default'),
            'payment.drivers' => $this->config->get('payment.drivers', []),
        ];

        if (! Schema::hasTable('tenant_payment_gateways')) {
            $this->applyDefaultGatewayFromSettings();

            return;
        }

        $rows = TenantPaymentGateway::query()->get(['gateway', 'credentials']);

        foreach ($rows as $row) {
            $credentials = is_array($row->credentials) ? $row->credentials : [];

            if ($credentials === []) {
                continue;
            }

            $driverKey = 'payment.drivers.'.$row->gateway;
            /** @var array<string, mixed> $existing */
            $existing = $this->config->get($driverKey, []);

            if (! is_array($existing)) {
                $existing = [];
            }

            $tenantProvidedWebhookSecret = false;

            foreach ($credentials as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }

                if ($value === null || $value === '') {
                    continue;
                }

                $existing[$key] = $value;

                if ($key === 'webhook_secret') {
                    $tenantProvidedWebhookSecret = true;
                }
            }

            // When the tenant stores only secret_key, use it for webhook HMAC too
            // (overrides env webhook_secret for this tenant context).
            if (
                ! $tenantProvidedWebhookSecret
                && isset($existing['secret_key'])
                && is_string($existing['secret_key'])
                && $existing['secret_key'] !== ''
            ) {
                $existing['webhook_secret'] = $existing['secret_key'];
            }

            $this->config->set($driverKey, $existing);
        }

        $this->applyDefaultGatewayFromSettings();
    }

    public function revert(): void
    {
        foreach ($this->originalConfig as $key => $value) {
            $this->config->set($key, $value);
        }

        $this->originalConfig = [];
    }

    /**
     * Optionally override payment.default from commerce settings.
     */
    protected function applyDefaultGatewayFromSettings(): void
    {
        if (! Schema::hasTable('commerce_settings')) {
            return;
        }

        $defaultGateway = CommerceSetting::query()
            ->where('key', 'payment.default_gateway')
            ->value('value');

        if (! is_string($defaultGateway) || $defaultGateway === '') {
            return;
        }

        $this->config->set('payment.default', $defaultGateway);
    }
}
