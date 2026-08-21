<?php

declare(strict_types=1);

namespace App\Services\Tenant\Payment;

use App\Models\Tenant\TenantPaymentGateway;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manage per-tenant payment gateway enablement and credentials.
 */
class TenantPaymentGatewayService
{
    /**
     * @var list<string>
     */
    private const SECRET_KEYS = [
        'secret_key',
        'secret',
        'api_secret',
        'secret_hash',
        'webhook_secret',
        'private_key',
        'password',
    ];

    /**
     * Create a new class instance.
     *
     * @param  PaymentManager  $paymentManager
     */
    public function __construct(private readonly PaymentManager $paymentManager) {}

    /**
     * Retrieve a paginated list of resources.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        return TenantPaymentGateway::query()
            ->orderBy('sort_order')
            ->orderBy('gateway')
            ->get()
            ->map(fn (TenantPaymentGateway $gateway): array => $this->toPublicArray($gateway));
    }

    /**
     * gateway: string, is_enabled?: bool|null, credentials?: array<string, mixed>|null, settings?: array<string, mixed>|null, sort_order?: int|null }  $data
     *
     * @param  array{
     *     gateway: string,
     *     is_enabled?: bool|null,
     *     credentials?: array<string, mixed>|null,
     *     settings?: array<string, mixed>|null,
     *     sort_order?: int|null
     * }  $data
     * @return array<string, mixed>
     */
    public function upsert(array $data): array
    {
        $gatewayName = Str::lower((string) $data['gateway']);

        if (! in_array($gatewayName, $this->paymentManager->drivers(), true)) {
            throw ValidationException::withMessages([
                'gateway' => "Unsupported payment gateway [{$gatewayName}].",
            ]);
        }

        /** @var TenantPaymentGateway $record */
        $record = TenantPaymentGateway::query()->firstOrNew(['gateway' => $gatewayName]);

        if (array_key_exists('is_enabled', $data) && $data['is_enabled'] !== null) {
            $record->is_enabled = (bool) $data['is_enabled'];
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $record->sort_order = (int) $data['sort_order'];
        }

        if (array_key_exists('settings', $data) && is_array($data['settings'])) {
            $record->settings = $data['settings'];
        }

        if (array_key_exists('credentials', $data) && is_array($data['credentials'])) {
            $existing = is_array($record->credentials) ? $record->credentials : [];
            $record->credentials = array_merge($existing, $this->sanitizeIncomingCredentials($data['credentials']));
        }

        $record->save();

        return $this->toPublicArray($record->fresh() ?? $record);
    }

    /**
     * Raw (decrypted) credentials for a gateway, or empty when unset / table missing.
     *
     * @param  string  $gateway
     * @return array<string, mixed>
     */
    public function credentialsFor(string $gateway): array
    {
        if (! Schema::hasTable('tenant_payment_gateways')) {
            return [];
        }

        $record = TenantPaymentGateway::query()
            ->where('gateway', Str::lower($gateway))
            ->first();

        return is_array($record?->credentials) ? $record->credentials : [];
    }

    /**
     * Enable.
     *
     * @param  string  $gateway
     * @return array
     */
    public function enable(string $gateway): array
    {
        return $this->setEnabled($gateway, true);
    }

    /**
     * Disable.
     *
     * @param  string  $gateway
     * @return array
     */
    public function disable(string $gateway): array
    {
        return $this->setEnabled($gateway, false);
    }

    /**
     * Resolve the preferred driver for a currency from enabled tenant gateways.
     *
     * @param  string  $currency
     * @return string
     */
    public function resolveDriverForCurrency(string $currency): string
    {
        $currency = Str::upper($currency);

        if (Schema::hasTable('tenant_payment_gateways')) {
            $enabled = TenantPaymentGateway::query()
                ->where('is_enabled', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($enabled as $row) {
                try {
                    $driver = $this->paymentManager->driver($row->gateway);
                } catch (\InvalidArgumentException) {
                    continue;
                }

                if ($driver->supportsCurrency($currency)) {
                    return $row->gateway;
                }
            }
        }

        /** @var array<string, string> $routing */
        $routing = config('payment.routing', []);

        if (isset($routing[$currency]) && is_string($routing[$currency]) && $routing[$currency] !== '') {
            return $routing[$currency];
        }

        return (string) config('payment.default', 'paystack');
    }

    /**
     * Set enabled.
     *
     * @param  string  $gateway
     * @param  bool  $enabled
     * @return array<string, mixed>
     */
    protected function setEnabled(string $gateway, bool $enabled): array
    {
        $gateway = Str::lower($gateway);

        if (! in_array($gateway, $this->paymentManager->drivers(), true)) {
            throw ValidationException::withMessages([
                'gateway' => "Unsupported payment gateway [{$gateway}].",
            ]);
        }

        /** @var TenantPaymentGateway $record */
        $record = TenantPaymentGateway::query()->firstOrCreate(
            ['gateway' => $gateway],
            ['is_enabled' => $enabled, 'sort_order' => 0],
        );

        $record->is_enabled = $enabled;
        $record->save();

        return $this->toPublicArray($record);
    }

    /**
     * Sanitize incoming credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    protected function sanitizeIncomingCredentials(array $credentials): array
    {
        $clean = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_string($value) && $this->looksMasked($value)) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    /**
     * To public array.
     *
     * @param  TenantPaymentGateway  $gateway
     * @return array<string, mixed>
     */
    protected function toPublicArray(TenantPaymentGateway $gateway): array
    {
        return [
            'id' => $gateway->id,
            'gateway' => $gateway->gateway,
            'is_enabled' => $gateway->is_enabled,
            'credentials' => $this->maskCredentials(is_array($gateway->credentials) ? $gateway->credentials : []),
            'settings' => $gateway->settings ?? [],
            'sort_order' => $gateway->sort_order,
            'created_at' => $gateway->created_at,
            'updated_at' => $gateway->updated_at,
        ];
    }

    /**
     * Mask credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    protected function maskCredentials(array $credentials): array
    {
        $masked = [];

        foreach ($credentials as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($this->isSecretKey($key) && is_string($value) && $value !== '') {
                $masked[$key] = $this->maskSecret($value);

                continue;
            }

            $masked[$key] = is_string($value) && $this->isSecretKey($key)
                ? $this->maskSecret($value)
                : $value;
        }

        return $masked;
    }

    /**
     * Is secret key.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isSecretKey(string $key): bool
    {
        $normalized = Str::lower($key);

        foreach (self::SECRET_KEYS as $secretKey) {
            if ($normalized === $secretKey || Str::contains($normalized, $secretKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask secret.
     *
     * @param  string  $value
     * @return string
     */
    protected function maskSecret(string $value): string
    {
        $length = Str::length($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max(4, $length - 4)).Str::substr($value, -4);
    }

    /**
     * Looks masked.
     *
     * @param  string  $value
     * @return bool
     */
    protected function looksMasked(string $value): bool
    {
        return Str::contains($value, '*');
    }
}
