<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\PaymentTransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Payment transaction record (central database). Never stores card/bank secrets.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $subscription_id
 * @property PaymentProvider $provider
 * @property string $reference
 * @property string $amount
 * @property string $currency
 * @property PaymentTransactionStatus $status
 */
class PaymentTransaction extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'provider',
        'provider_transaction_id',
        'reference',
        'amount',
        'currency',
        'status',
        'paid_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentTransactionStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
