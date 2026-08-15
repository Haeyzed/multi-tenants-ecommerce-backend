<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Enums\Landlord\BillingInterval;
use App\Models\Landlord\Plan;
use App\Services\Landlord\Plan\PlanService;
use Illuminate\Database\Seeder;

/**
 * Seeds default subscription plans and their feature entitlements.
 */
class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(FeatureSeeder::class);

        /** @var PlanService $planService */
        $planService = app(PlanService::class);

        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Get started with core store capabilities.',
                'price' => '0.00',
                'currency' => 'NGN',
                'billing_interval' => BillingInterval::Monthly->value,
                'trial_days' => 0,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 1,
                'features' => [
                    ['feature' => 'products', 'enabled' => true, 'limit' => 50],
                    ['feature' => 'orders', 'enabled' => true, 'limit' => 100],
                    ['feature' => 'customers', 'enabled' => true, 'limit' => 100],
                    ['feature' => 'inventory', 'enabled' => false],
                    ['feature' => 'advanced-reports', 'enabled' => false],
                    ['feature' => 'custom-domain', 'enabled' => false],
                    ['feature' => 'api-access', 'enabled' => false],
                    ['feature' => 'gift-cards', 'enabled' => false],
                    ['feature' => 'store-credit', 'enabled' => false],
                    ['feature' => 'loyalty', 'enabled' => false],
                ],
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For growing stores that need more capacity.',
                'price' => '15000.00',
                'currency' => 'NGN',
                'billing_interval' => BillingInterval::Monthly->value,
                'trial_days' => 7,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 2,
                'features' => [
                    ['feature' => 'products', 'enabled' => true, 'limit' => 500],
                    ['feature' => 'orders', 'enabled' => true, 'limit' => 1000],
                    ['feature' => 'customers', 'enabled' => true, 'limit' => 1000],
                    ['feature' => 'inventory', 'enabled' => true, 'limit' => 1],
                    ['feature' => 'advanced-reports', 'enabled' => false],
                    ['feature' => 'custom-domain', 'enabled' => false],
                    ['feature' => 'api-access', 'enabled' => false],
                    ['feature' => 'gift-cards', 'enabled' => false],
                    ['feature' => 'store-credit', 'enabled' => false],
                    ['feature' => 'loyalty', 'enabled' => false],
                ],
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Advanced tools for established businesses.',
                'price' => '45000.00',
                'currency' => 'NGN',
                'billing_interval' => BillingInterval::Monthly->value,
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 3,
                'features' => [
                    ['feature' => 'products', 'enabled' => true, 'limit' => 5000],
                    ['feature' => 'orders', 'enabled' => true, 'limit' => null],
                    ['feature' => 'customers', 'enabled' => true, 'limit' => null],
                    ['feature' => 'inventory', 'enabled' => true, 'limit' => 5],
                    ['feature' => 'advanced-reports', 'enabled' => true],
                    ['feature' => 'custom-domain', 'enabled' => true],
                    ['feature' => 'api-access', 'enabled' => true],
                    ['feature' => 'gift-cards', 'enabled' => true],
                    ['feature' => 'store-credit', 'enabled' => true],
                    ['feature' => 'loyalty', 'enabled' => true],
                ],
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'Full platform access for larger operations.',
                'price' => '99000.00',
                'currency' => 'NGN',
                'billing_interval' => BillingInterval::Monthly->value,
                'trial_days' => 14,
                'is_active' => true,
                'is_public' => true,
                'sort_order' => 4,
                'features' => [
                    ['feature' => 'products', 'enabled' => true, 'limit' => null],
                    ['feature' => 'orders', 'enabled' => true, 'limit' => null],
                    ['feature' => 'customers', 'enabled' => true, 'limit' => null],
                    ['feature' => 'inventory', 'enabled' => true, 'limit' => null],
                    ['feature' => 'advanced-reports', 'enabled' => true],
                    ['feature' => 'custom-domain', 'enabled' => true],
                    ['feature' => 'api-access', 'enabled' => true],
                    ['feature' => 'gift-cards', 'enabled' => true],
                    ['feature' => 'store-credit', 'enabled' => true],
                    ['feature' => 'loyalty', 'enabled' => true],
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $features = $planData['features'];
            unset($planData['features']);

            $plan = Plan::query()->updateOrCreate(
                ['slug' => $planData['slug']],
                $planData,
            );

            $planService->syncFeatures($plan, $features);
        }
    }
}
