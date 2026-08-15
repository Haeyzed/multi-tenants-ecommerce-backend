<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\Tenant\Customer\CustomerSegmentRule;
use App\Models\Tenant\CustomerSegment;
use Illuminate\Database\Seeder;

/**
 * Seeds the built-in customer segments evaluated on the fly.
 */
class CustomerSegmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->segments() as $index => $segment) {
            CustomerSegment::query()->updateOrCreate(
                ['slug' => $segment['slug']],
                [
                    'name' => $segment['name'],
                    'description' => $segment['description'],
                    'rules' => [
                        'match' => 'all',
                        'conditions' => $segment['conditions'],
                    ],
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, name: string, description: string, conditions: list<array{type: string, value?: mixed}>}>
     */
    private function segments(): array
    {
        return [
            [
                'slug' => 'new-customers',
                'name' => 'New Customers',
                'description' => 'Registered customers who have not placed an order yet.',
                'conditions' => [['type' => CustomerSegmentRule::NewCustomer->value]],
            ],
            [
                'slug' => 'returning-customers',
                'name' => 'Returning Customers',
                'description' => 'Customers with at least one non-cancelled order.',
                'conditions' => [['type' => CustomerSegmentRule::ReturningCustomer->value]],
            ],
            [
                'slug' => 'high-value-customers',
                'name' => 'High Value Customers',
                'description' => 'Customers whose lifetime spend reaches the configured threshold.',
                'conditions' => [['type' => CustomerSegmentRule::HighValue->value, 'value' => '1000.00']],
            ],
            [
                'slug' => 'frequent-buyers',
                'name' => 'Frequent Buyers',
                'description' => 'Customers with five or more non-cancelled orders.',
                'conditions' => [['type' => CustomerSegmentRule::FrequentBuyer->value, 'value' => 5]],
            ],
            [
                'slug' => 'inactive-customers',
                'name' => 'Inactive Customers',
                'description' => 'Past buyers with no order in the last 90 days.',
                'conditions' => [['type' => CustomerSegmentRule::Inactive->value, 'value' => 90]],
            ],
            [
                'slug' => 'wishlist-customers',
                'name' => 'Wishlist Customers',
                'description' => 'Customers with at least one saved wishlist item.',
                'conditions' => [['type' => CustomerSegmentRule::WishlistCustomer->value]],
            ],
            [
                'slug' => 'abandoned-cart-customers',
                'name' => 'Abandoned Cart Customers',
                'description' => 'Customers with an abandoned shopping cart.',
                'conditions' => [['type' => CustomerSegmentRule::AbandonedCartCustomer->value]],
            ],
        ];
    }
}
