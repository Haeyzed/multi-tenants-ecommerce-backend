<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Commerce\GiftCardStatus;
use App\Models\Tenant\GiftCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GiftCard>
 */
class GiftCardFactory extends Factory
{
    /**
     * @var class-string<GiftCard>
     */
    protected $model = GiftCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Str::upper(Str::random(16));

        return [
            'code_hash' => hash('sha256', $code),
            'last_four' => substr($code, -4),
            'initial_amount' => '100.00',
            'balance' => '100.00',
            'currency' => 'NGN',
            'status' => GiftCardStatus::Active,
            'expires_at' => now()->addYear(),
            'activated_at' => now(),
            'customer_id' => null,
            'purchased_order_id' => null,
            'meta' => null,
        ];
    }

    /**
     * Build the card from a known plain code so tests can redeem it.
     */
    public function withCode(string $code): static
    {
        $normalized = Str::upper(trim($code));

        return $this->state(fn (): array => [
            'code_hash' => hash('sha256', $normalized),
            'last_four' => substr($normalized, -4),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => GiftCardStatus::Inactive,
            'activated_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function balance(string $amount): static
    {
        return $this->state(fn (): array => [
            'initial_amount' => $amount,
            'balance' => $amount,
        ]);
    }
}
