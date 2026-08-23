<?php

declare(strict_types=1);

test('cors configuration allows api paths for browser clients', function (): void {
    $cors = config('cors');

    expect($cors)->toBeArray()
        ->and($cors['paths'])->toContain('api/*')
        ->and($cors['allowed_methods'])->toBe(['*'])
        ->and($cors['allowed_origins'])->toBe(['*'])
        ->and($cors['allowed_headers'])->toBe(['*'])
        ->and($cors['supports_credentials'])->toBeFalse();
});
