<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\Commerce\GiftCardTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry for a gift card balance movement.
 *
 * Amounts are signed decimal strings: positive for funds added to the card
 * (activation, refund restore) and negative for redemptions.
 *
 * @property int $id
 * @property int $gift_card_id
 * @property GiftCardTransactionType $type
 * @property string $amount
 * @property string $balance_after
 * @property int|null $order_id
 * @property string|null $description
 */
class GiftCardTransaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'gift_card_id',
        'type',
        'amount',
        'balance_after',
        'order_id',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gift_card_id' => 'integer',
            'type' => GiftCardTransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'order_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<GiftCard, $this>
     */
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
