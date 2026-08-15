<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('world index validation failures return the standardized api envelope', function () {
    $response = $this->getJson('/api/world/countries?filters[iso2]=TOOLONG');

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The given data was invalid.')
        ->assertJsonPath('data', null)
        ->assertJsonPath('meta', null)
        ->assertJsonStructure([
            'errors' => [
                'filters.iso2',
            ],
        ]);
});
