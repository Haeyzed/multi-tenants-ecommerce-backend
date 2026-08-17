<?php

declare(strict_types=1);

use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

test('default scramble docs route is disabled', function (): void {
    $this->get('http://localhost/docs/api')->assertNotFound();
});

test('landlord and tenant apis are registered with scramble', function (): void {
    $apis = array_keys(Scramble::getConfigurationsInstance()->all());

    expect($apis)->toContain('landlord', 'tenant');
});

test('tenant docs use a subdomain server variable', function (): void {
    $servers = Scramble::getGeneratorConfig('tenant')->get('servers');

    expect($servers)->toBeArray()
        ->and($servers['Live'] ?? null)->toContain('{tenant}');
});

test('landlord docs document the api path on the app domain', function (): void {
    $config = Scramble::getGeneratorConfig('landlord');

    expect($config->get('api_path'))->toBe('api')
        ->and($config->get('api_domain'))->toBe(parse_url((string) config('app.url'), PHP_URL_HOST));
});

test('landlord openapi document serves from cache', function (): void {
    cache()->store(config('scramble.cache.store'))->forever(
        config('scramble.cache.key').':landlord',
        [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Landlord API', 'version' => '1.0.0'],
            'paths' => [],
        ],
    );

    $this->get('http://localhost/docs/landlord.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'Landlord API');
});

test('tenant openapi document serves from cache', function (): void {
    cache()->store(config('scramble.cache.store'))->forever(
        config('scramble.cache.key').':tenant',
        [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Tenant API', 'version' => '1.0.0'],
            'paths' => [],
        ],
    );

    $this->get('http://localhost/docs/tenant.json')
        ->assertOk()
        ->assertJsonPath('info.title', 'Tenant API');
});

test('landlord docs include central api routes and exclude tenant api routes', function (): void {
    $filter = Scramble::getGeneratorConfig('landlord')->routes();
    $documented = collect(RouteFacade::getRoutes())->filter(fn (Route $route): bool => $filter($route));

    expect($documented->isNotEmpty())->toBeTrue()
        ->and($documented->every(fn (Route $route): bool => Str::startsWith($route->uri, 'api')))->toBeTrue()
        ->and($documented->every(fn (Route $route): bool => $route->getDomain() !== null))->toBeTrue();
});

test('tenant docs include tenant api routes and exclude landlord api routes', function (): void {
    $filter = Scramble::getGeneratorConfig('tenant')->routes();
    $documented = collect(RouteFacade::getRoutes())->filter(fn (Route $route): bool => $filter($route));

    expect($documented->isNotEmpty())->toBeTrue()
        ->and($documented->every(fn (Route $route): bool => Str::startsWith($route->uri, 'api/')))->toBeTrue()
        ->and($documented->every(fn (Route $route): bool => $route->getDomain() === null))->toBeTrue();
});
