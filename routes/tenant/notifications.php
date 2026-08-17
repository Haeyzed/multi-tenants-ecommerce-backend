<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\Notification\DeviceController as NotificationDeviceController;
use App\Http\Controllers\Tenant\Notification\InboxController as NotificationInboxController;
use App\Http\Controllers\Tenant\Notification\PreferenceController as NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::get('notifications/unread-count', [NotificationInboxController::class, 'unreadCount'])->name('tenant.notifications.unread-count');
Route::get('notifications/unread', [NotificationInboxController::class, 'unread'])->name('tenant.notifications.unread');
Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllRead'])->name('tenant.notifications.read-all');
Route::get('notifications', [NotificationInboxController::class, 'index'])->name('tenant.notifications.index');
Route::get('notifications/{notification}', [NotificationInboxController::class, 'show'])->name('tenant.notifications.show');
Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('tenant.notifications.read');
Route::post('notifications/{notification}/unread', [NotificationInboxController::class, 'markUnread'])->name('tenant.notifications.unread-one');
Route::delete('notifications/{notification}', [NotificationInboxController::class, 'destroy'])->name('tenant.notifications.destroy');

Route::get('notification-preferences', [NotificationPreferenceController::class, 'index'])->name('tenant.notification-preferences.index');
Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('tenant.notification-preferences.update');

Route::get('devices', [NotificationDeviceController::class, 'index'])->name('tenant.devices.index');
Route::post('devices', [NotificationDeviceController::class, 'store'])->name('tenant.devices.store');
Route::delete('devices/{device}', [NotificationDeviceController::class, 'destroy'])->whereNumber('device')->name('tenant.devices.destroy');
