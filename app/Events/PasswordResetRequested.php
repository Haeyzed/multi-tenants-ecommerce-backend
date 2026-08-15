<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a password reset is requested for an existing user.
 */
class PasswordResetRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Model $user,
        public readonly string $token,
    ) {}
}
