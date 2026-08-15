<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only commerce analytics event.
 *
 * @property int $id
 * @property string $event_name
 * @property int|null $customer_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $payload
 * @property Carbon $occurred_at
 */
class CommerceEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_name',
        'customer_id',
        'subject_type',
        'subject_id',
        'payload',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'subject_id' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
