<?php

declare(strict_types=1);

use App\Models\Landlord\World\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    config([
        'world.geolocate.database_path' => storage_path('app/geoip/missing-for-tests.mmdb'),
        'world.geolocate.fallback_api' => true,
    ]);

    Country::query()->create([
        'iso2' => 'US',
        'iso3' => 'USA',
        'name' => 'United States',
        'status' => 1,
        'phone_code' => '1',
        'region' => 'Americas',
        'subregion' => 'Northern America',
        'native' => 'United States',
        'latitude' => '38.00000000',
        'longitude' => '-97.00000000',
        'emoji' => '🇺🇸',
        'emojiU' => 'U+1F1FA U+1F1F8',
    ]);
});

test('it geolocates an ip address and links world country data', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'country' => 'United States',
            'countryCode' => 'US',
            'region' => 'VA',
            'regionName' => 'Virginia',
            'city' => 'Ashburn',
            'zip' => '20149',
            'lat' => 39.03,
            'lon' => -77.5,
            'timezone' => 'America/New_York',
        ]),
    ]);

    $this->getJson('/api/world/geolocate?ip=8.8.8.8')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Location retrieved successfully.')
        ->assertJsonPath('data.ip', '8.8.8.8')
        ->assertJsonPath('data.country.iso2', 'US')
        ->assertJsonPath('data.country.name', 'United States')
        ->assertJsonPath('data.city.name', 'Ashburn')
        ->assertJsonPath('data.coordinates.latitude', 39.03)
        ->assertJsonPath('data.postal_code', '20149')
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'ip',
                'country',
                'state',
                'city',
                'coordinates',
                'timezone',
                'postal_code',
            ],
            'meta',
            'errors',
        ]);
});

test('it returns the current client ip', function () {
    $this->withServerVariables([
        'REMOTE_ADDR' => '8.8.8.8',
    ])->getJson('/api/world/geolocate/ip')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'IP retrieved successfully.')
        ->assertJsonPath('data.ip', '8.8.8.8');
});

test('it falls back to a public ip lookup for private client addresses', function () {
    Http::fake([
        'api.ipify.org*' => Http::response([
            'ip' => '162.120.187.117',
        ]),
        'ip-api.com/*' => Http::response([
            'query' => '162.120.187.117',
        ]),
    ]);

    $this->withServerVariables([
        'REMOTE_ADDR' => '127.0.0.1',
    ])->getJson('/api/world/geolocate/ip')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.ip', '162.120.187.117');
});

test('it rejects an invalid ip address', function () {
    $this->getJson('/api/world/geolocate?ip=not-an-ip')
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['ip']]);
});

test('it returns not found for private ip addresses', function () {
    $this->getJson('/api/world/geolocate?ip=127.0.0.1')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});
