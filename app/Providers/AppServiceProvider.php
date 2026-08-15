<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Notification\PushNotificationProvider;
use App\Contracts\Notification\SmsProvider;
use App\Contracts\Payment\PaymentGateway;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Policies\Tenant\BrandPolicy;
use App\Policies\Tenant\CategoryPolicy;
use App\Services\Notification\ChannelResolver;
use App\Services\Notification\Channels\DatabaseChannel;
use App\Services\Notification\Channels\EmailChannel;
use App\Services\Notification\Channels\PushChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\DeviceTokenService;
use App\Services\Notification\NotificationPreferenceService;
use App\Services\Notification\Push\FcmPushProvider;
use App\Services\Notification\Sms\SmsManager;
use App\Services\Payment\PaymentManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentManager::class);

        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            return $app->make(PaymentManager::class)->driver();
        });

        $this->app->singleton(PushNotificationProvider::class, FcmPushProvider::class);
        $this->app->singleton(SmsManager::class);
        $this->app->bind(SmsProvider::class, function ($app): SmsProvider {
            return $app->make(SmsManager::class)->driver();
        });

        $this->app->singleton(ChannelResolver::class, function ($app): ChannelResolver {
            return new ChannelResolver(
                channels: [
                    $app->make(DatabaseChannel::class),
                    $app->make(EmailChannel::class),
                    $app->make(PushChannel::class),
                    $app->make(SmsChannel::class),
                ],
                preferences: $app->make(NotificationPreferenceService::class),
                devices: $app->make(DeviceTokenService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);

        Gate::define('viewApiDocs', function ($user = null): bool {
            // RestrictedDocsAccess already allows local; this gate covers non-local environments.
            return app()->environment(['local', 'testing']);
        });

        Gate::before(function ($user, string $ability): ?bool {
            if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
