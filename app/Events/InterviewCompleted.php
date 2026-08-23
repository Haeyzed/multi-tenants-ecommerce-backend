<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\HR\Interview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InterviewCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Interview $interview) {}
}
