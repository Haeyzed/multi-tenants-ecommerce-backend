<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\ServerVariable;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;

/**
 * Registers separate Scramble OpenAPI documentation sites for landlord and tenant APIs.
 *
 * Landlord docs are bound to central domains only.
 * Tenant docs require Stancl domain tenancy and are blocked on central hosts.
 */
class ScrambleDocumentationServiceProvider extends ServiceProvider
{
    /**
     * Disable Scramble's default /docs/api routes before they are registered.
     */
    public function register(): void
    {
        Scramble::ignoreDefaultRoutes();
    }

    /**
     * Register landlord and tenant documentation APIs and expose their routes.
     */
    public function boot(): void
    {
        // Unknown hostnames must not fall through to a default tenant.
        InitializeTenancyByDomain::$onFail ??= function (): never {
            abort(404, 'Tenant not found.');
        };

        $this->registerLandlordDocumentation();
        $this->registerTenantDocumentation();
    }

    /**
     * Landlord (central) API documentation at /docs/landlord.
     */
    protected function registerLandlordDocumentation(): void
    {
        Scramble::registerApi('landlord', [
            'api_path' => 'api',
            'info' => [
                'version' => config('scramble.info.version', '1.0.0'),
                'description' => 'Management of the SaaS platform and tenant infrastructure (central/landlord API).',
            ],
            'ui' => [
                'title' => 'Multi-Tenants E-commerce API - Landlord',
            ],
            'servers' => [
                'Landlord' => 'api',
            ],
            'middleware' => [],
            'security_strategy' => [
                MiddlewareAuthSecurityStrategy::class,
                [
                    'middleware' => ['auth', 'auth:*'],
                    'scheme' => SecurityScheme::http('bearer'),
                ],
            ],
        ])
            ->routes(fn (Route $route): bool => $this->isLandlordDocumentedRoute($route))
            ->expose(
                ui: fn (Router $router, mixed $action): Route => $this->registerCentralDocsRoute(
                    $router,
                    'docs/landlord',
                    $action,
                    'scramble.landlord.docs.ui',
                ),
                document: fn (Router $router, mixed $action): Route => $this->registerCentralDocsRoute(
                    $router,
                    'docs/landlord/openapi.json',
                    $action,
                    'scramble.landlord.docs.document',
                ),
            );
    }

    /**
     * Tenant API documentation at /docs/tenant (tenant domains only).
     */
    protected function registerTenantDocumentation(): void
    {
        $centralHost = $this->centralHost();

        Scramble::registerApi('tenant', [
            'api_path' => 'api',
            'info' => [
                'version' => config('scramble.info.version', '1.0.0'),
                'description' => 'Tenant-specific e-commerce operations. Resolve the tenant from the request hostname (Stancl domain tenancy).',
            ],
            'ui' => [
                'title' => 'Multi-Tenants E-commerce API - Tenant',
            ],
            'servers' => [
                'Current tenant' => 'api',
                'Tenant template' => 'https://{tenant}.'.$centralHost.'/api',
            ],
            'middleware' => [],
            'security_strategy' => [
                MiddlewareAuthSecurityStrategy::class,
                [
                    'middleware' => ['auth', 'auth:*'],
                    'scheme' => SecurityScheme::http('bearer'),
                ],
            ],
        ])
            ->routes(fn (Route $route): bool => $this->isTenantDocumentedRoute($route))
            ->withServerVariables([
                'tenant' => ServerVariable::make(
                    default: 'tenant1',
                    description: 'Tenant subdomain (or full host label) used with Stancl domain identification.',
                ),
            ])
            ->expose(
                ui: fn (Router $router, mixed $action): Route => $router
                    ->middleware($this->tenantDocsMiddleware())
                    ->get('docs/tenant', $action)
                    ->name('scramble.tenant.docs.ui'),
                document: fn (Router $router, mixed $action): Route => $router
                    ->middleware($this->tenantDocsMiddleware())
                    ->get('docs/tenant/openapi.json', $action)
                    ->name('scramble.tenant.docs.document'),
            );
    }

    /**
     * Middleware stack for tenant documentation (Stancl domain identification + access gate).
     *
     * @return list<string|class-string>
     */
    protected function tenantDocsMiddleware(): array
    {
        return [
            'web',
            'tenant',
            InitializeTenancyByDomain::class,
            PreventAccessFromUnwantedDomains::class,
            RestrictedDocsAccess::class,
        ];
    }

    /**
     * Bind documentation UI/JSON to every configured central domain.
     */
    protected function registerCentralDocsRoute(Router $router, string $uri, mixed $action, string $name): Route
    {
        $domains = config('tenancy.identification.central_domains', []);
        $last = null;

        foreach ($domains as $index => $domain) {
            $last = $router
                ->domain($domain)
                ->middleware(['web', 'central', RestrictedDocsAccess::class])
                ->get($uri, $action)
                ->name($index === 0 ? $name : $name.'.'.$index);
        }

        if ($last === null) {
            $last = $router
                ->middleware(['web', 'central', RestrictedDocsAccess::class])
                ->get($uri, $action)
                ->name($name);
        }

        return $last;
    }

    /**
     * Whether the route belongs to the landlord/central API surface.
     */
    protected function isLandlordDocumentedRoute(Route $route): bool
    {
        if (! $this->isApiRoute($route)) {
            return false;
        }

        $action = $route->getActionName();

        if ($action === 'Closure') {
            return false;
        }

        return Str::startsWith($action, 'App\\Http\\Controllers\\Landlord\\')
            || Str::startsWith($action, 'App\\Http\\Controllers\\Public\\')
            || Str::startsWith($action, 'App\\Http\\Controllers\\Webhook\\');
    }

    /**
     * Whether the route belongs to the tenant API surface.
     */
    protected function isTenantDocumentedRoute(Route $route): bool
    {
        if (! $this->isApiRoute($route)) {
            return false;
        }

        $action = $route->getActionName();

        if ($action === 'Closure') {
            return false;
        }

        return Str::startsWith($action, 'App\\Http\\Controllers\\Tenant\\');
    }

    /**
     * Scramble only documents application API routes under the api prefix.
     */
    protected function isApiRoute(Route $route): bool
    {
        return Str::startsWith($route->uri(), 'api')
            || Str::startsWith($route->uri(), '/api');
    }

    /**
     * Resolve the configured central hostname used for tenant OpenAPI server templates.
     */
    protected function centralHost(): string
    {
        $fromAppUrl = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($fromAppUrl) && $fromAppUrl !== '') {
            return $fromAppUrl;
        }

        $central = config('tenancy.identification.central_domains', []);

        return is_array($central) && isset($central[0]) ? (string) $central[0] : 'localhost';
    }
}
