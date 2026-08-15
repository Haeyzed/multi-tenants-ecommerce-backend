<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Return merchandise authorization lifecycle.
 */
enum ReturnStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case AwaitingReturn = 'awaiting_return';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Inspecting = 'inspecting';
    case ApprovedForRefund = 'approved_for_refund';
    case RefundProcessing = 'refund_processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
