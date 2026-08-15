<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a landlord or tenant user account is created.
 */
class UserCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Model $user) {}
}
