<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Feature\FeatureController;
use App\Http\Controllers\Landlord\Plan\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('features/options', [FeatureController::class, 'options'])->middleware('permission:features.view')->name('landlord.features.options');
Route::get('features', [FeatureController::class, 'index'])->middleware('permission:features.view')->name('landlord.features.index');
Route::post('features', [FeatureController::class, 'store'])->middleware('permission:features.create')->name('landlord.features.store');
Route::get('features/{feature}', [FeatureController::class, 'show'])->middleware('permission:features.show')->whereNumber('feature')->name('landlord.features.show');
Route::match(['put', 'patch'], 'features/{feature}', [FeatureController::class, 'update'])->middleware('permission:features.update')->whereNumber('feature')->name('landlord.features.update');
Route::delete('features/{feature}', [FeatureController::class, 'destroy'])->middleware('permission:features.delete')->whereNumber('feature')->name('landlord.features.destroy');

Route::get('plans/options', [PlanController::class, 'options'])->middleware('permission:plans.view')->name('landlord.plans.options');
Route::get('plans', [PlanController::class, 'index'])->middleware('permission:plans.view')->name('landlord.plans.index');
Route::post('plans', [PlanController::class, 'store'])->middleware('permission:plans.create')->name('landlord.plans.store');
Route::get('plans/{plan}', [PlanController::class, 'show'])->middleware('permission:plans.show')->whereNumber('plan')->name('landlord.plans.show');
Route::match(['put', 'patch'], 'plans/{plan}', [PlanController::class, 'update'])->middleware('permission:plans.update')->whereNumber('plan')->name('landlord.plans.update');
Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:plans.delete')->whereNumber('plan')->name('landlord.plans.destroy');
Route::put('plans/{plan}/features', [PlanController::class, 'syncFeatures'])->middleware('permission:plans.update')->whereNumber('plan')->name('landlord.plans.features');
