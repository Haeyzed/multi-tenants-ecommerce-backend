<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Domain\DomainController;
use App\Http\Controllers\Landlord\Subscription\SubscriptionController as LandlordSubscriptionController;
use App\Http\Controllers\Landlord\Tenant\TenantController;
use App\Http\Controllers\Landlord\TenantProfile\TenantProfileController;
use Illuminate\Support\Facades\Route;

Route::get('tenants/options', [TenantController::class, 'options'])->middleware('permission:tenants.view')->name('landlord.tenants.options');
Route::get('tenants', [TenantController::class, 'index'])->middleware('permission:tenants.view')->name('landlord.tenants.index');
Route::post('tenants', [TenantController::class, 'store'])->middleware('permission:tenants.create')->name('landlord.tenants.store');
Route::get('tenants/{tenant}', [TenantController::class, 'show'])->middleware('permission:tenants.show')->name('landlord.tenants.show');
Route::match(['put', 'patch'], 'tenants/{tenant}', [TenantController::class, 'update'])->middleware('permission:tenants.update')->name('landlord.tenants.update');
Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->middleware('permission:tenants.delete')->name('landlord.tenants.destroy');

Route::get('tenants/{tenant}/domains', [DomainController::class, 'index'])->middleware('permission:domains.view')->name('landlord.tenants.domains.index');
Route::post('tenants/{tenant}/domains', [DomainController::class, 'store'])->middleware('permission:domains.create')->name('landlord.tenants.domains.store');
Route::get('tenants/{tenant}/domains/{domain}', [DomainController::class, 'show'])->middleware('permission:domains.show')->whereNumber('domain')->name('landlord.tenants.domains.show');
Route::match(['put', 'patch'], 'tenants/{tenant}/domains/{domain}', [DomainController::class, 'update'])->middleware('permission:domains.update')->whereNumber('domain')->name('landlord.tenants.domains.update');
Route::delete('tenants/{tenant}/domains/{domain}', [DomainController::class, 'destroy'])->middleware('permission:domains.delete')->whereNumber('domain')->name('landlord.tenants.domains.destroy');
Route::post('tenants/{tenant}/domains/{domain}/primary', [DomainController::class, 'makePrimary'])->middleware('permission:domains.update')->whereNumber('domain')->name('landlord.tenants.domains.primary');

Route::get('tenants/{tenant}/profile', [TenantProfileController::class, 'show'])->middleware('permission:tenant-profiles.show')->name('landlord.tenants.profile.show');
Route::post('tenants/{tenant}/profile', [TenantProfileController::class, 'store'])->middleware('permission:tenant-profiles.create')->name('landlord.tenants.profile.store');
Route::match(['put', 'patch'], 'tenants/{tenant}/profile', [TenantProfileController::class, 'update'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.update');
Route::delete('tenants/{tenant}/profile', [TenantProfileController::class, 'destroy'])->middleware('permission:tenant-profiles.delete')->name('landlord.tenants.profile.destroy');
Route::post('tenants/{tenant}/profile/logo', [TenantProfileController::class, 'storeLogo'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.logo.store');
Route::delete('tenants/{tenant}/profile/logo', [TenantProfileController::class, 'destroyLogo'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.logo.destroy');
Route::post('tenants/{tenant}/profile/cover', [TenantProfileController::class, 'storeCover'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.cover.store');
Route::delete('tenants/{tenant}/profile/cover', [TenantProfileController::class, 'destroyCover'])->middleware('permission:tenant-profiles.update')->name('landlord.tenants.profile.cover.destroy');

Route::get('tenants/{tenant}/subscription', [LandlordSubscriptionController::class, 'current'])->middleware('permission:subscriptions.show')->name('landlord.tenants.subscription.current');
Route::post('tenants/{tenant}/subscription/subscribe', [LandlordSubscriptionController::class, 'subscribe'])->middleware('permission:subscriptions.create')->name('landlord.tenants.subscription.subscribe');
Route::post('tenants/{tenant}/subscription/verify', [LandlordSubscriptionController::class, 'verify'])->middleware('permission:subscriptions.update')->name('landlord.tenants.subscription.verify');
Route::post('tenants/{tenant}/subscription/{subscription}/cancel', [LandlordSubscriptionController::class, 'cancel'])->middleware('permission:subscriptions.update')->whereNumber('subscription')->name('landlord.tenants.subscription.cancel');
Route::post('tenants/{tenant}/subscription/change-plan', [LandlordSubscriptionController::class, 'changePlan'])->middleware('permission:subscriptions.update')->name('landlord.tenants.subscription.change-plan');
