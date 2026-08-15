<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\OrderReturn;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderReturnReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public OrderReturn $orderReturn) {}
}
