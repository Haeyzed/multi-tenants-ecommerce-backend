<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Materialized customer membership in a rule-based segment.
 *
 * @property int $id
 * @property int $customer_segment_id
 * @property int $customer_id
 * @property Carbon $entered_at
 */
class CustomerSegmentMember extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_segment_id',
        'customer_id',
        'entered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CustomerSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class, 'customer_segment_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
