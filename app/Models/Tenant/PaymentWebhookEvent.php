<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tenant payment provider webhook event for idempotent processing.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string|null $event_type
 * @property string|null $reference
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 */
class PaymentWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'reference',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
