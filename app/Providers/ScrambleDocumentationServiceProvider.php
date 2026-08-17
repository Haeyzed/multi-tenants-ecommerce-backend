<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * API documentation websites for landlord and tenant.
 *
 * @see https://scramble.dedoc.co/usage/multiple-docs
 * @see https://scramble.dedoc.co/blog/multitenant-apis
 */
class ScrambleDocumentationServiceProvider extends ServiceProvider
{
    /**
     * Disable the default /docs/api routes.
     */
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Register landlord and tenant documentation websites.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', function ($user = null): bool {
            return ! app()->isProduction();
        });

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        Scramble::registerApi('landlord', [
            'api_path' => 'api',
            'api_domain' => $host,
            'ui' => [
                'title' => 'Landlord API',
            ],
            'servers' => [
                'Live' => 'api',
            ],
        ])->expose(
            ui: '/docs/landlord',
            document: '/docs/landlord.json',
        );

        Scramble::registerApi('tenant', [
            'api_path' => 'api',
            'ui' => [
                'title' => 'Tenant API',
            ],
            'servers' => [
                'Live' => 'https://{tenant}.'.$host.'/api',
            ],
        ])
            ->routes(function (Route $route): bool {
                return Str::startsWith($route->uri, 'api/')
                    && $route->getDomain() === null;
            })
            ->withServerVariables([
                'tenant' => new ServerVariable(
                    default: 'demo',
                    description: 'The tenant name.',
                ),
            ])
            ->expose(
                ui: '/docs/tenant',
                document: '/docs/tenant.json',
            );
    }
}
