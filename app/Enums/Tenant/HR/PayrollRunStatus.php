<?php

declare(strict_types=1);

namespace App\Enums\Tenant\HR;

/**
 * Payroll run lifecycle status.
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Processed = 'processed';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * Whether the run can be edited or regenerated.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Whether the run can be marked paid.
     */
    public function canPay(): bool
    {
        return $this === self::Processed;
    }

    /**
     * Whether the run can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::Processed], true);
    }
}
