<?php

declare(strict_types=1);

test('mysql unique index identifiers stay within 64 characters', function (): void {
    $named = [
        'variant_option_value_unique',
        'product_attr_value_unique',
        'inventories_warehouse_stockable_unique',
        'product_stock_subscriptions_unique',
        'cart_items_offer_unique',
        'cart_items_line_unique',
        'job_applications_opening_candidate_unique',
        'product_reviews_customer_product_unique',
        'product_bundle_items_unique',
        'customer_segment_members_unique',
        'notification_preferences_unique',
        'tax_zone_locations_geo_index',
    ];

    foreach ($named as $name) {
        expect(strlen($name))->toBeLessThanOrEqual(64);
    }
});

test('laravel default unique names that exceed mysql limit are given aliases', function (string $table, array $columns, string $alias): void {
    $generated = str_replace(['-', '.'], '_', strtolower($table.'_'.implode('_', $columns).'_unique'));

    expect(strlen($generated))->toBeGreaterThan(64)
        ->and(strlen($alias))->toBeLessThanOrEqual(64);
})->with([
    ['product_variant_option_value', ['product_variant_id', 'product_option_value_id'], 'variant_option_value_unique'],
    ['product_attribute_product', ['product_id', 'product_attribute_value_id'], 'product_attr_value_unique'],
    ['inventories', ['warehouse_id', 'inventoryable_type', 'inventoryable_id'], 'inventories_warehouse_stockable_unique'],
    ['product_stock_subscriptions', ['customer_id', 'product_id', 'product_variant_id'], 'product_stock_subscriptions_unique'],
    ['cart_items', ['cart_id', 'product_id', 'product_variant_id', 'seller_offer_id'], 'cart_items_offer_unique'],
]);
