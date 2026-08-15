<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Catalog;

/**
 * Reason codes for inventory quantity changes.
 */
enum InventoryMovementType: string
{
    case OpeningStock = 'opening_stock';
    case Purchase = 'purchase';
    case PurchaseReturn = 'purchase_return';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Damaged = 'damaged';
    case Expired = 'expired';
}
