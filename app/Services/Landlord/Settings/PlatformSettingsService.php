<?php

declare(strict_types=1);

namespace App\Services\Landlord\Settings;

use App\Models\Landlord\PlatformSetting;
use Illuminate\Validation\ValidationException;

/**
 * Key/value platform settings on the central database.
 */
class PlatformSettingsService
{
    /**
     * Domain → key → schema (type + default).
     *
     * @var array<string, array<string, array{type: string, default: mixed}>>
     */
    public const array DOMAINS = [
        'platform' => [
            'platform.name' => ['type' => 'string', 'default' => 'Multi-tenant Ecommerce'],
            'platform.support_email' => ['type' => 'nullable_string', 'default' => null],
            'platform.support_phone' => ['type' => 'nullable_string', 'default' => null],
            'platform.maintenance_mode' => ['type' => 'bool', 'default' => false],
            'platform.maintenance_message' => ['type' => 'nullable_string', 'default' => null],
        ],
        'registration' => [
            'registration.tenant_registration_enabled' => ['type' => 'bool', 'default' => true],
            'registration.default_plan_slug' => ['type' => 'nullable_string', 'default' => null],
        ],
        'localization' => [
            'localization.default_currency' => ['type' => 'string', 'default' => 'NGN'],
            'localization.default_timezone' => ['type' => 'string', 'default' => 'UTC'],
        ],
    ];

    /**
     * Read a platform setting value.
     *
     * @param  string  $key
     * @param  ?string  $default
     * @return ?string
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $setting = PlatformSetting::query()->where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Persist a platform setting value.
     *
     * @param  string  $key
     * @param  ?string  $value
     * @return void
     */
    public function set(string $key, ?string $value): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Known setting keys for a domain, cast with defaults.
     *
     * @param  string  $domain
     * @return array<string, mixed>
     */
    public function getDomain(string $domain): array
    {
        $schema = $this->domainSchema($domain);
        $settings = [];

        foreach ($schema as $key => $definition) {
            $raw = $this->get($key);
            $settings[$key] = $this->castValue(
                $raw,
                $definition['type'],
                $definition['default'],
            );
        }

        return $settings;
    }

    /**
     * Validate against the domain allowlist and persist values.
     *
     * @param  string  $domain
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function updateDomain(string $domain, array $values): array
    {
        $schema = $this->domainSchema($domain);
        $unknown = array_diff(array_keys($values), array_keys($schema));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => ['Unknown setting keys for domain ['.$domain.']: '.implode(', ', $unknown).'.'],
            ]);
        }

        foreach ($values as $key => $value) {
            $this->set($key, $this->serializeValue($value, $schema[$key]['type']));
        }

        return $this->getDomain($domain);
    }

    /**
     * Domains.
     *
     * @return list<string>
     */
    public function domains(): array
    {
        return array_keys(self::DOMAINS);
    }

    /**
     * Whether the given domain is a platform settings domain.
     *
     * @param  string  $domain
     * @return bool
     */
    public function hasDomain(string $domain): bool
    {
        return array_key_exists($domain, self::DOMAINS);
    }

    /**
     * Domain schema.
     *
     * @param  string  $domain
     * @return array<string, array{type: string, default: mixed}>
     */
    protected function domainSchema(string $domain): array
    {
        if (! $this->hasDomain($domain)) {
            throw ValidationException::withMessages([
                'domain' => ['Unknown settings domain ['.$domain.'].'],
            ]);
        }

        return self::DOMAINS[$domain];
    }

    /**
     * Cast value.
     *
     * @param  ?string  $raw
     * @param  string  $type
     * @param  mixed  $default
     * @return mixed
     */
    protected function castValue(?string $raw, string $type, mixed $default): mixed
    {
        if ($raw === null) {
            return $default;
        }

        return match ($type) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $raw,
            'nullable_int' => $raw === '' ? null : (int) $raw,
            'float' => (float) $raw,
            'money', 'string' => $raw,
            'nullable_string' => $raw === '' ? null : $raw,
            default => $raw,
        };
    }

    /**
     * Serialize value.
     *
     * @param  mixed  $value
     * @param  string  $type
     * @return ?string
     */
    protected function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return match ($type) {
                'nullable_string', 'nullable_int' => null,
                'bool' => 'false',
                'int', 'float', 'money' => '0',
                default => null,
            };
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'int', 'nullable_int' => (string) (int) $value,
            'float' => (string) (float) $value,
            'money' => is_numeric($value) ? (string) $value : (string) $value,
            'string', 'nullable_string' => (string) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
