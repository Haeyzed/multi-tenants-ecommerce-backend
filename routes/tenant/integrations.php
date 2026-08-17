<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Integration\IntegrationTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations')->middleware('feature:api-access')->name('tenant.integrations.')->group(function (): void {
    Route::get('tokens', [IntegrationTokenController::class, 'index'])->middleware('permission:integrations.view')->name('tokens.index');
    Route::post('tokens', [IntegrationTokenController::class, 'store'])->middleware('permission:integrations.create')->name('tokens.store');
    Route::delete('tokens/{token}', [IntegrationTokenController::class, 'destroy'])->middleware('permission:integrations.delete')->whereNumber('token')->name('tokens.destroy');
});
