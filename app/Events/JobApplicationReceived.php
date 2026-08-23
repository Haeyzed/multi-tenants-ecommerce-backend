<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\HR\JobApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobApplicationReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public JobApplication $application) {}
}
