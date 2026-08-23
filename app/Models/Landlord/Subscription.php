<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\PaymentProvider;
use App\Enums\Landlord\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tenant subscription to a plan (central database).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property PaymentProvider|null $provider
 */
class Subscription extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'provider',
        'provider_subscription_id',
        'provider_customer_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'current_period_start',
        'current_period_end',
        'auto_renew',
        'cancel_at_period_end',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'auto_renew' => 'boolean',
            'cancel_at_period_end' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Whether this subscription currently grants feature access.
     */
    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    /**
     * When paid/trial access ends for lifecycle and reminders.
     */
    public function accessEndsAt(): ?Carbon
    {
        if ($this->ends_at !== null) {
            return $this->ends_at;
        }

        if ($this->status === SubscriptionStatus::Trialing && $this->trial_ends_at !== null) {
            return $this->trial_ends_at;
        }

        return $this->current_period_end;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<PaymentTransaction, $this>
     */
    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
