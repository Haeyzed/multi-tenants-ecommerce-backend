<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user changes or successfully resets their password.
 */
class PasswordChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $user,
        public readonly string $reason = 'changed',
    ) {}
}
