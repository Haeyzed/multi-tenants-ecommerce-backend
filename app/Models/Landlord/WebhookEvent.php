<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PaymentProvider;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Processed payment provider webhook event for idempotency.
 *
 * @property int $id
 * @property PaymentProvider $provider
 * @property string $event_id
 * @property string|null $event_type
 */
class WebhookEvent extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
