<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when HR approves or rejects a leave request.
 */
class LeaveReviewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest) {}
}
