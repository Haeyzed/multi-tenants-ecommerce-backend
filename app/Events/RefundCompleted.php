<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Refund $refund) {}
}
