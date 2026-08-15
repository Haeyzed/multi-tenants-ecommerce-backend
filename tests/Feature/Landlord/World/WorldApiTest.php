<?php

declare(strict_types=1);

use App\Models\Landlord\World\City;
use App\Models\Landlord\World\Country;
use App\Models\Landlord\World\Currency;
use App\Models\Landlord\World\Language;
use App\Models\Landlord\World\State;
use App\Models\Landlord\World\Timezone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $country = Country::query()->create([
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

    $state = State::query()->create([
        'country_id' => $country->id,
        'name' => 'California',
        'country_code' => 'US',
        'state_code' => 'CA',
        'type' => 'state',
        'latitude' => '36.77830000',
        'longitude' => '-119.41790000',
    ]);

    City::query()->create([
        'country_id' => $country->id,
        'state_id' => $state->id,
        'name' => 'Los Angeles',
        'country_code' => 'US',
        'state_code' => 'CA',
        'latitude' => '34.05220000',
        'longitude' => '-118.24370000',
    ]);

    Currency::query()->create([
        'country_id' => $country->id,
        'name' => 'US Dollar',
        'code' => 'USD',
        'precision' => 2,
        'symbol' => '$',
        'symbol_native' => '$',
        'symbol_first' => true,
        'decimal_mark' => '.',
        'thousands_separator' => ',',
    ]);

    Timezone::query()->create([
        'country_id' => $country->id,
        'name' => 'America/Los_Angeles',
    ]);

    Language::query()->create([
        'code' => 'en',
        'name' => 'English',
        'name_native' => 'English',
        'dir' => 'ltr',
    ]);
});

test('it lists countries with pagination meta', function () {
    $response = $this->getJson('/api/world/countries');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Countries retrieved successfully.')
        ->assertJsonPath('errors', null)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => ['id', 'iso2', 'name'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            'errors',
        ]);
});

test('it returns country options as label and value pairs', function () {
    $country = Country::query()->firstOrFail();

    $this->getJson('/api/world/countries/options')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.label', 'United States')
        ->assertJsonPath('data.0.value', $country->id);
});

test('it shows a country by id', function () {
    $country = Country::query()->firstOrFail();

    $this->getJson('/api/world/countries/'.$country->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $country->id)
        ->assertJsonPath('data.iso2', 'US');
});

test('it searches countries by search query', function () {
    Country::query()->create([
        'iso2' => 'NG',
        'iso3' => 'NGA',
        'name' => 'Nigeria',
        'status' => 1,
        'phone_code' => '234',
        'region' => 'Africa',
        'subregion' => 'Western Africa',
        'native' => 'Nigeria',
        'latitude' => '10.00000000',
        'longitude' => '8.00000000',
        'emoji' => '🇳🇬',
        'emojiU' => 'U+1F1F3 U+1F1EC',
    ]);

    $this->getJson('/api/world/countries?search=United')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'United States');

    $this->getJson('/api/world/countries/options?search=United')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.label', 'United States');
});

test('it filters states by country code', function () {
    $this->getJson('/api/world/states?filters[country_code]=US')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'California');
});

test('it filters cities by state id', function () {
    $state = State::query()->firstOrFail();

    $this->getJson('/api/world/cities?filters[state_id]='.$state->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.name', 'Los Angeles');
});

test('it lists currencies timezones and languages with options endpoints', function () {
    $this->getJson('/api/world/currencies')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/world/timezones')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/world/languages')->assertOk()->assertJsonPath('meta.total', 1);

    $this->getJson('/api/world/currencies/options')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'US Dollar (USD)');

    $this->getJson('/api/world/states/options?filters[country_code]=US')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'California');
});

test('it returns not found for missing world resources', function () {
    $this->getJson('/api/world/countries/999999')->assertNotFound();
});
