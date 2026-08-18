<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Interview;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InterviewCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public Interview $interview) {}
}
