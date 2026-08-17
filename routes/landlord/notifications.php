<?php

declare(strict_types=1);

use App\Http\Controllers\Landlord\Notification\DeviceController as NotificationDeviceController;
use App\Http\Controllers\Landlord\Notification\InboxController as NotificationInboxController;
use App\Http\Controllers\Landlord\Notification\NotificationTemplateController;
use App\Http\Controllers\Landlord\Notification\PreferenceController as NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

Route::get('notifications/unread-count', [NotificationInboxController::class, 'unreadCount'])->name('landlord.notifications.unread-count');
Route::get('notifications/unread', [NotificationInboxController::class, 'unread'])->name('landlord.notifications.unread');
Route::post('notifications/read-all', [NotificationInboxController::class, 'markAllRead'])->name('landlord.notifications.read-all');
Route::get('notifications', [NotificationInboxController::class, 'index'])->name('landlord.notifications.index');
Route::get('notifications/{notification}', [NotificationInboxController::class, 'show'])->name('landlord.notifications.show');
Route::post('notifications/{notification}/read', [NotificationInboxController::class, 'markRead'])->name('landlord.notifications.read');
Route::post('notifications/{notification}/unread', [NotificationInboxController::class, 'markUnread'])->name('landlord.notifications.unread-one');
Route::delete('notifications/{notification}', [NotificationInboxController::class, 'destroy'])->name('landlord.notifications.destroy');

Route::get('notification-preferences', [NotificationPreferenceController::class, 'index'])->name('landlord.notification-preferences.index');
Route::put('notification-preferences', [NotificationPreferenceController::class, 'update'])->name('landlord.notification-preferences.update');

Route::get('devices', [NotificationDeviceController::class, 'index'])->name('landlord.devices.index');
Route::post('devices', [NotificationDeviceController::class, 'store'])->name('landlord.devices.store');
Route::delete('devices/{device}', [NotificationDeviceController::class, 'destroy'])->whereNumber('device')->name('landlord.devices.destroy');

Route::get('notification-templates/options', [NotificationTemplateController::class, 'options'])->middleware('permission:notification-templates.view')->name('landlord.notification-templates.options');
Route::get('notification-templates', [NotificationTemplateController::class, 'index'])->middleware('permission:notification-templates.view')->name('landlord.notification-templates.index');
Route::post('notification-templates', [NotificationTemplateController::class, 'store'])->middleware('permission:notification-templates.create')->name('landlord.notification-templates.store');
Route::get('notification-templates/{notification_template}', [NotificationTemplateController::class, 'show'])->middleware('permission:notification-templates.show')->whereNumber('notification_template')->name('landlord.notification-templates.show');
Route::match(['put', 'patch'], 'notification-templates/{notification_template}', [NotificationTemplateController::class, 'update'])->middleware('permission:notification-templates.update')->whereNumber('notification_template')->name('landlord.notification-templates.update');
Route::delete('notification-templates/{notification_template}', [NotificationTemplateController::class, 'destroy'])->middleware('permission:notification-templates.delete')->whereNumber('notification_template')->name('landlord.notification-templates.destroy');
Route::post('notification-templates/{notification_template}/preview', [NotificationTemplateController::class, 'preview'])->middleware('permission:notification-templates.view')->whereNumber('notification_template')->name('landlord.notification-templates.preview');
