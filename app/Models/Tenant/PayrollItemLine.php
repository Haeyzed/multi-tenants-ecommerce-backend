<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\HR\PayrollLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Earning or deduction line on a payslip.
 *
 * @property int $id
 * @property int $payroll_item_id
 * @property PayrollLineType $type
 * @property string $code
 * @property string $label
 * @property string $amount
 */
class PayrollItemLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payroll_item_id',
        'type',
        'code',
        'label',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payroll_item_id' => 'integer',
            'type' => PayrollLineType::class,
        ];
    }

    /**
     * @return BelongsTo<PayrollItem, $this>
     */
    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }
}
