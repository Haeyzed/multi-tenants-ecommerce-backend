<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\Tenant\HR\JobApplicationStatus;
use App\Models\HR\JobApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobApplicationStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JobApplication $application,
        public JobApplicationStatus $fromStatus,
        public JobApplicationStatus $toStatus,
    ) {}
}
