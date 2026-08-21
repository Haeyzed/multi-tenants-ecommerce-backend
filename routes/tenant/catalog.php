<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Catalog\CollectionController;
use App\Http\Controllers\Tenant\Catalog\ProductAttributeController;
use App\Http\Controllers\Tenant\Catalog\ProductBadgeController;
use App\Http\Controllers\Tenant\Catalog\ProductOptionController;
use App\Http\Controllers\Tenant\Catalog\ProductTagController;
use App\Http\Controllers\Tenant\Catalog\SeoController;
use App\Http\Controllers\Tenant\Category\CategoryController;
use App\Http\Controllers\Tenant\Inventory\InventoryController;
use App\Http\Controllers\Tenant\Unit\UnitController;
use App\Http\Controllers\Tenant\Warehouse\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::get('categories/options', [CategoryController::class, 'options'])->middleware('permission:categories.view')->name('tenant.categories.options');
Route::get('categories/tree', [CategoryController::class, 'tree'])->middleware('permission:categories.view')->name('tenant.categories.tree');
Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:categories.view')->name('tenant.categories.index');
Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:categories.create')->name('tenant.categories.store');
Route::get('categories/{category}/children', [CategoryController::class, 'children'])->middleware('permission:categories.view')->whereNumber('category')->name('tenant.categories.children');
Route::get('categories/{category}', [CategoryController::class, 'show'])->middleware('permission:categories.show')->whereNumber('category')->name('tenant.categories.show');
Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.update');
Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:categories.delete')->whereNumber('category')->name('tenant.categories.destroy');
Route::post('categories/{category}/image', [CategoryController::class, 'storeImage'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.image.store');
Route::delete('categories/{category}/image', [CategoryController::class, 'destroyImage'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.image.destroy');
Route::get('categories/{category}/seo', [SeoController::class, 'showCategory'])->middleware('permission:categories.show')->whereNumber('category')->name('tenant.categories.seo.show');
Route::match(['put', 'patch'], 'categories/{category}/seo', [SeoController::class, 'upsertCategory'])->middleware('permission:categories.update')->whereNumber('category')->name('tenant.categories.seo.upsert');

Route::get('collections', [CollectionController::class, 'index'])->middleware('permission:collections.view')->name('tenant.collections.index');
Route::post('collections', [CollectionController::class, 'store'])->middleware('permission:collections.create')->name('tenant.collections.store');
Route::get('collections/{collection}', [CollectionController::class, 'show'])->middleware('permission:collections.show')->whereNumber('collection')->name('tenant.collections.show');
Route::match(['put', 'patch'], 'collections/{collection}', [CollectionController::class, 'update'])->middleware('permission:collections.update')->whereNumber('collection')->name('tenant.collections.update');
Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->middleware('permission:collections.delete')->whereNumber('collection')->name('tenant.collections.destroy');
Route::post('collections/{collection}/products', [CollectionController::class, 'syncProducts'])->middleware('permission:collections.update')->whereNumber('collection')->name('tenant.collections.products.sync');
Route::get('collections/{collection}/seo', [SeoController::class, 'showCollection'])->middleware('permission:collections.show')->whereNumber('collection')->name('tenant.collections.seo.show');
Route::match(['put', 'patch'], 'collections/{collection}/seo', [SeoController::class, 'upsertCollection'])->middleware('permission:collections.update')->whereNumber('collection')->name('tenant.collections.seo.upsert');

Route::get('tags/options', [ProductTagController::class, 'options'])->middleware('permission:tags.view')->name('tenant.tags.options');
Route::get('tags', [ProductTagController::class, 'index'])->middleware('permission:tags.view')->name('tenant.tags.index');
Route::post('tags', [ProductTagController::class, 'store'])->middleware('permission:tags.create')->name('tenant.tags.store');
Route::get('tags/{tag}', [ProductTagController::class, 'show'])->middleware('permission:tags.show')->whereNumber('tag')->name('tenant.tags.show');
Route::match(['put', 'patch'], 'tags/{tag}', [ProductTagController::class, 'update'])->middleware('permission:tags.update')->whereNumber('tag')->name('tenant.tags.update');
Route::delete('tags/{tag}', [ProductTagController::class, 'destroy'])->middleware('permission:tags.delete')->whereNumber('tag')->name('tenant.tags.destroy');

Route::get('badges/options', [ProductBadgeController::class, 'options'])->middleware('permission:badges.view')->name('tenant.badges.options');
Route::get('badges', [ProductBadgeController::class, 'index'])->middleware('permission:badges.view')->name('tenant.badges.index');
Route::post('badges', [ProductBadgeController::class, 'store'])->middleware('permission:badges.create')->name('tenant.badges.store');
Route::get('badges/{badge}', [ProductBadgeController::class, 'show'])->middleware('permission:badges.show')->whereNumber('badge')->name('tenant.badges.show');
Route::match(['put', 'patch'], 'badges/{badge}', [ProductBadgeController::class, 'update'])->middleware('permission:badges.update')->whereNumber('badge')->name('tenant.badges.update');
Route::delete('badges/{badge}', [ProductBadgeController::class, 'destroy'])->middleware('permission:badges.delete')->whereNumber('badge')->name('tenant.badges.destroy');

Route::get('options/options', [ProductOptionController::class, 'options'])->middleware('permission:options.view')->name('tenant.options.options');
Route::get('options', [ProductOptionController::class, 'index'])->middleware('permission:options.view')->name('tenant.options.index');
Route::post('options', [ProductOptionController::class, 'store'])->middleware('permission:options.create')->name('tenant.options.store');
Route::get('options/{option}', [ProductOptionController::class, 'show'])->middleware('permission:options.show')->whereNumber('option')->name('tenant.options.show');
Route::match(['put', 'patch'], 'options/{option}', [ProductOptionController::class, 'update'])->middleware('permission:options.update')->whereNumber('option')->name('tenant.options.update');
Route::delete('options/{option}', [ProductOptionController::class, 'destroy'])->middleware('permission:options.delete')->whereNumber('option')->name('tenant.options.destroy');
Route::post('options/{option}/values', [ProductOptionController::class, 'storeValue'])->middleware('permission:options.update')->whereNumber('option')->name('tenant.options.values.store');
Route::match(['put', 'patch'], 'options/{option}/values/{value}', [ProductOptionController::class, 'updateValue'])->middleware('permission:options.update')->whereNumber(['option', 'value'])->name('tenant.options.values.update');
Route::delete('options/{option}/values/{value}', [ProductOptionController::class, 'destroyValue'])->middleware('permission:options.update')->whereNumber(['option', 'value'])->name('tenant.options.values.destroy');

Route::get('attributes/options', [ProductAttributeController::class, 'options'])->middleware('permission:attributes.view')->name('tenant.attributes.options');
Route::get('attributes', [ProductAttributeController::class, 'index'])->middleware('permission:attributes.view')->name('tenant.attributes.index');
Route::post('attributes', [ProductAttributeController::class, 'store'])->middleware('permission:attributes.create')->name('tenant.attributes.store');
Route::get('attributes/{attribute}', [ProductAttributeController::class, 'show'])->middleware('permission:attributes.show')->whereNumber('attribute')->name('tenant.attributes.show');
Route::match(['put', 'patch'], 'attributes/{attribute}', [ProductAttributeController::class, 'update'])->middleware('permission:attributes.update')->whereNumber('attribute')->name('tenant.attributes.update');
Route::delete('attributes/{attribute}', [ProductAttributeController::class, 'destroy'])->middleware('permission:attributes.delete')->whereNumber('attribute')->name('tenant.attributes.destroy');
Route::post('attributes/{attribute}/values', [ProductAttributeController::class, 'storeValue'])->middleware('permission:attributes.update')->whereNumber('attribute')->name('tenant.attributes.values.store');
Route::match(['put', 'patch'], 'attributes/{attribute}/values/{value}', [ProductAttributeController::class, 'updateValue'])->middleware('permission:attributes.update')->whereNumber(['attribute', 'value'])->name('tenant.attributes.values.update');
Route::delete('attributes/{attribute}/values/{value}', [ProductAttributeController::class, 'destroyValue'])->middleware('permission:attributes.update')->whereNumber(['attribute', 'value'])->name('tenant.attributes.values.destroy');

Route::get('units/options', [UnitController::class, 'options'])->middleware('permission:units.view')->name('tenant.units.options');
Route::get('units', [UnitController::class, 'index'])->middleware('permission:units.view')->name('tenant.units.index');
Route::post('units', [UnitController::class, 'store'])->middleware('permission:units.create')->name('tenant.units.store');
Route::get('units/{unit}', [UnitController::class, 'show'])->middleware('permission:units.show')->whereNumber('unit')->name('tenant.units.show');
Route::match(['put', 'patch'], 'units/{unit}', [UnitController::class, 'update'])->middleware('permission:units.update')->whereNumber('unit')->name('tenant.units.update');
Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:units.delete')->whereNumber('unit')->name('tenant.units.destroy');

Route::get('warehouses/options', [WarehouseController::class, 'options'])->middleware('permission:warehouses.view')->name('tenant.warehouses.options');
Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view')->name('tenant.warehouses.index');
Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create')->name('tenant.warehouses.store');
Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.show')->whereNumber('warehouse')->name('tenant.warehouses.show');
Route::get('warehouses/{warehouse}/inventory', [InventoryController::class, 'indexForWarehouse'])->middleware('permission:inventory.view')->whereNumber('warehouse')->name('tenant.warehouses.inventory.index');
Route::match(['put', 'patch'], 'warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update')->whereNumber('warehouse')->name('tenant.warehouses.update');
Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete')->whereNumber('warehouse')->name('tenant.warehouses.destroy');
Route::get('warehouses/{warehouse}/locations', [WarehouseController::class, 'indexLocations'])->middleware('permission:warehouses.view')->whereNumber('warehouse')->name('tenant.warehouses.locations.index');
Route::post('warehouses/{warehouse}/locations', [WarehouseController::class, 'storeLocation'])->middleware('permission:warehouses.update')->whereNumber('warehouse')->name('tenant.warehouses.locations.store');
Route::match(['put', 'patch'], 'warehouses/{warehouse}/locations/{location}', [WarehouseController::class, 'updateLocation'])->middleware('permission:warehouses.update')->whereNumber(['warehouse', 'location'])->name('tenant.warehouses.locations.update');
Route::delete('warehouses/{warehouse}/locations/{location}', [WarehouseController::class, 'destroyLocation'])->middleware('permission:warehouses.update')->whereNumber(['warehouse', 'location'])->name('tenant.warehouses.locations.destroy');
