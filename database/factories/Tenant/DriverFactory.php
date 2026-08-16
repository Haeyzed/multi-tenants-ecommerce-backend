<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Driver\DriverAvailability;
use App\Enums\Tenant\Driver\DriverStatus;
use App\Models\Tenant\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @var class-string<Driver>
     */
    protected $model = Driver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => DriverStatus::Active,
            'availability' => DriverAvailability::Available,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the driver is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DriverStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the driver is blocked.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DriverStatus::Blocked,
        ]);
    }

    /**
     * Indicate that the driver is unavailable.
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'availability' => DriverAvailability::Unavailable,
        ]);
    }
}
