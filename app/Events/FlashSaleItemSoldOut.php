<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\FlashSaleItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlashSaleItemSoldOut
{
    use Dispatchable, SerializesModels;

    public function __construct(public FlashSaleItem $flashSaleItem) {}
}
