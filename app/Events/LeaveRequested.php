<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\LeaveRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an employee submits a leave request.
 */
class LeaveRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public LeaveRequest $leaveRequest) {}
}
