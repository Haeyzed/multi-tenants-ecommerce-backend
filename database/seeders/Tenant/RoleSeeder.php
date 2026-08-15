<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Support\RbacPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds tenant Spatie roles.
 */
class RoleSeeder extends Seeder
{
    /**
     * Guard name for tenant roles.
     */
    private const string GUARD = 'tenant';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::findOrCreate('admin', self::GUARD);
        $admin->syncPermissions(RbacPermissions::NAMES);

        $manager = Role::findOrCreate('manager', self::GUARD);
        $manager->syncPermissions([
            'users.view',
            'users.create',
            'users.show',
            'users.update',
            'roles.view',
            'roles.show',
            'permissions.view',
            'permissions.show',
            'brands.view',
            'brands.create',
            'brands.show',
            'brands.update',
            'brands.delete',
            'categories.view',
            'categories.create',
            'categories.show',
            'categories.update',
            'categories.delete',
            'units.view',
            'units.create',
            'units.show',
            'units.update',
            'units.delete',
            'warehouses.view',
            'warehouses.create',
            'warehouses.show',
            'warehouses.update',
            'warehouses.delete',
            'products.view',
            'products.create',
            'products.show',
            'products.update',
            'products.delete',
            'variants.view',
            'variants.create',
            'variants.show',
            'variants.update',
            'variants.delete',
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'customers.view',
            'customers.show',
            'customers.update',
            'collections.view',
            'collections.create',
            'collections.show',
            'collections.update',
            'tags.view',
            'tags.create',
            'tags.show',
            'tags.update',
            'badges.view',
            'badges.create',
            'badges.show',
            'badges.update',
            'reviews.view',
            'reviews.moderate',
            'options.view',
            'options.create',
            'options.show',
            'options.update',
            'attributes.view',
            'attributes.create',
            'attributes.show',
            'attributes.update',
            'orders.view',
            'orders.show',
            'orders.update',
            'orders.cancel',
            'payments.view',
            'payments.verify',
            'shipping.view',
            'shipping.manage',
            'shipments.view',
            'shipments.manage',
            'suppliers.view',
            'suppliers.create',
            'suppliers.show',
            'suppliers.update',
            'suppliers.delete',
            'procurement.view',
            'procurement.create',
            'procurement.update',
            'procurement.approve',
            'procurement.receive',
            'accounting.view',
            'accounting.manage',
            'journal_entries.create',
            'journal_entries.post',
            'sellers.view',
            'sellers.create',
            'sellers.update',
            'sellers.approve',
            'sellers.reject',
            'sellers.suspend',
            'seller_offers.view',
            'seller_offers.create',
            'seller_offers.update',
            'seller_offers.delete',
            'seller_orders.view',
            'seller_orders.manage',
            'commissions.view',
            'commissions.manage',
            'payouts.view',
            'payouts.manage',
            'taxes.view',
            'taxes.create',
            'taxes.update',
            'taxes.delete',
            'invoices.view',
            'invoices.generate',
            'invoices.download',
            'refunds.view',
            'refunds.create',
            'refunds.process',
            'returns.view',
            'returns.create',
            'returns.approve',
            'returns.reject',
            'returns.inspect',
            'returns.complete',
            'coupons.view',
            'coupons.create',
            'coupons.show',
            'coupons.update',
            'coupons.delete',
            'promotions.view',
            'promotions.create',
            'promotions.show',
            'promotions.update',
            'promotions.delete',
        ]);

        $seller = Role::findOrCreate('seller', self::GUARD);
        $seller->syncPermissions([
            'seller_offers.view',
            'seller_offers.create',
            'seller_offers.update',
            'seller_offers.delete',
            'seller_orders.view',
            'seller_orders.manage',
            'commissions.view',
            'payouts.view',
            'orders.view',
            'orders.show',
            'shipments.view',
            'returns.view',
            'returns.inspect',
        ]);

        Role::findOrCreate('customer', self::GUARD);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
