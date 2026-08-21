<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\ReturnStatus;
use App\Models\Tenant\OrderReturn;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Valid OrderReturn status transitions.
 */
class OrderReturnTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'requested' => ['under_review', 'cancelled'],
        'under_review' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['awaiting_return', 'cancelled'],
        'rejected' => [],
        'awaiting_return' => ['in_transit', 'received', 'cancelled'],
        'in_transit' => ['received', 'cancelled'],
        'received' => ['inspecting'],
        'inspecting' => ['approved_for_refund', 'rejected'],
        'approved_for_refund' => ['refund_processing'],
        'refund_processing' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Transition a return to a new status.
     *
     * @param  OrderReturn  $return
     * @param  ReturnStatus  $to
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function transition(OrderReturn $return, ReturnStatus $to): OrderReturn
    {
        $from = $return->status->value;
        $allowed = self::ALLOWED[$from] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition return from {$from} to {$to->value}.",
            ]);
        }

        $return->status = $to;

        match ($to) {
            ReturnStatus::Approved => $return->approved_at = Carbon::now(),
            ReturnStatus::Rejected => $return->rejected_at = Carbon::now(),
            ReturnStatus::Received => $return->received_at = Carbon::now(),
            ReturnStatus::Completed => $return->completed_at = Carbon::now(),
            default => null,
        };

        $return->save();

        return $return->fresh(['items.orderItem', 'order', 'customer']) ?? $return;
    }
}
