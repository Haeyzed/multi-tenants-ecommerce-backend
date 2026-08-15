<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Models\Tenant\CustomerSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerSegment>
 */
class CustomerSegmentFactory extends Factory
{
    /**
     * @var class-string<CustomerSegment>
     */
    protected $model = CustomerSegment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'rules' => [
                'match' => 'all',
                'conditions' => [
                    ['type' => CustomerSegmentRule::ReturningCustomer->value],
                ],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Build a segment around a single built-in rule.
     */
    public function rule(CustomerSegmentRule $rule, int|string|null $value = null): static
    {
        return $this->state(fn (): array => [
            'rules' => [
                'match' => 'all',
                'conditions' => [
                    array_filter(
                        ['type' => $rule->value, 'value' => $value],
                        fn (mixed $item): bool => $item !== null,
                    ),
                ],
            ],
        ]);
    }
}
