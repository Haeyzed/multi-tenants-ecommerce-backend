<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

test('migrations drop foreign keys before dropping composite uniques on mysql', function (): void {
    $mustDropForeignKeysBeforeUnique = [
        'database/migrations/tenant/2026_08_15_070004_add_seller_offer_to_cart_and_order_items.php',
        'database/migrations/tenant/2026_08_15_090203_add_unique_customer_product_to_product_reviews_table.php',
        'database/migrations/tenant/2026_08_18_154939_add_candidate_and_stage_to_job_applications_table.php',
    ];

    foreach ($mustDropForeignKeysBeforeUnique as $path) {
        $source = file_get_contents(base_path($path));

        expect($source)
            ->toContain('ForeignKeyIndexHelper::dropForeignKeys')
            ->and($source)->toContain('dropUnique');
    }
});

test('cart items create migration uses a short named line unique index', function (): void {
    $source = file_get_contents(base_path('database/migrations/tenant/2026_08_15_060004_create_cart_items_table.php'));

    expect($source)->toContain("'cart_items_line_unique'");
});
