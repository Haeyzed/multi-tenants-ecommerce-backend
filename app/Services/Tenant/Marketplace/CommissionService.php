<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\CommissionType;
use App\Enums\Tenant\Marketplace\SellerCommissionStatus;
use App\Enums\Tenant\Marketplace\SellerOrderStatus;
use App\Models\Tenant\Order;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\SellerOrder;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Support\Money;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marketplace commission calculation and accrual.
 */
class CommissionService
{
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
        private readonly SellerOrderService $sellerOrders,
    ) {}

    /**
     * Create commission records for all seller orders on a paid order.
     */
    public function createForOrder(Order $order): void
    {
        if (! $this->commerceSettings->isMarketplaceEnabled()) {
            return;
        }

        $order->loadMissing('items');

        if ($order->items->every(fn ($item): bool => $item->seller_id === null)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            $sellerOrders = SellerOrder::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            foreach ($sellerOrders as $sellerOrder) {
                if (SellerCommission::query()->where('seller_order_id', $sellerOrder->id)->exists()) {
                    continue;
                }

                $sellerOrder->loadMissing('seller.sellerGroup');
                $seller = $sellerOrder->seller;

                if ($seller === null) {
                    continue;
                }

                $calculation = $this->calculate($seller, (string) $sellerOrder->subtotal);

                SellerCommission::query()->create([
                    'seller_order_id' => $sellerOrder->id,
                    'seller_id' => $sellerOrder->seller_id,
                    'order_id' => $order->id,
                    'commission_type' => $calculation['commission_type'],
                    'commission_rate' => $calculation['commission_rate'],
                    'commission_fixed_amount' => $calculation['commission_fixed_amount'],
                    'order_subtotal' => (string) $sellerOrder->subtotal,
                    'commission_amount' => $calculation['commission_amount'],
                    'seller_amount' => $calculation['seller_amount'],
                    'status' => SellerCommissionStatus::Earned,
                    'earned_at' => now(),
                ]);

                $sellerOrder->commission_total = $calculation['commission_amount'];
                $sellerOrder->seller_total = $calculation['seller_amount'];
                $sellerOrder->status = SellerOrderStatus::Confirmed;
                $sellerOrder->save();
            }

            $this->sellerOrders->confirmForPaidOrder($order);
        });
    }

    /**
     * @return array{
     *     commission_type: CommissionType,
     *     commission_rate: string|null,
     *     commission_fixed_amount: string|null,
     *     commission_amount: string,
     *     seller_amount: string
     * }
     */
    public function calculate(Seller $seller, string $subtotal): array
    {
        $type = $seller->commission_type
            ?? $seller->sellerGroup?->commission_type
            ?? CommissionType::from($this->commerceSettings->defaultCommissionType());
        $rate = $seller->commission_rate
            ?? $seller->sellerGroup?->commission_rate
            ?? $this->commerceSettings->defaultCommissionRate();
        $fixed = $seller->commission_fixed_amount
            ?? $seller->sellerGroup?->commission_fixed_amount
            ?? $this->commerceSettings->defaultCommissionFixedAmount();

        $commissionAmount = match ($type) {
            CommissionType::Percentage => Money::percent($subtotal, $rate),
            CommissionType::Fixed => Money::add($fixed, '0'),
            CommissionType::PercentagePlusFixed => Money::add(Money::percent($subtotal, $rate), $fixed),
        };

        if (bccomp($commissionAmount, $subtotal, 2) > 0) {
            $commissionAmount = $subtotal;
        }

        $sellerAmount = Money::sub($subtotal, $commissionAmount);

        return [
            'commission_type' => $type,
            'commission_rate' => $type === CommissionType::Fixed ? null : $rate,
            'commission_fixed_amount' => in_array($type, [CommissionType::Fixed, CommissionType::PercentagePlusFixed], true)
                ? $fixed
                : null,
            'commission_amount' => $commissionAmount,
            'seller_amount' => $sellerAmount,
        ];
    }

    /**
     * @param  array{
     *     seller_id?: int|null,
     *     order_id?: int|null,
     *     status?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, SellerCommission>
     */
    public function list(array $params = [], ?Authenticatable $actor = null): LengthAwarePaginator
    {
        $query = SellerCommission::query()
            ->with(['seller', 'order', 'sellerOrder'])
            ->latest('id');

        if ($actor instanceof Seller) {
            $query->where('seller_id', $actor->id);
        } elseif (! empty($params['seller_id'])) {
            $query->where('seller_id', $params['seller_id']);
        }

        if (! empty($params['order_id'])) {
            $query->where('order_id', $params['order_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    public function show(SellerCommission $commission): SellerCommission
    {
        return $commission->load(['seller', 'order', 'sellerOrder', 'payouts']);
    }

    /**
     * @throws ValidationException
     */
    public function assertEligibleForPayout(SellerCommission $commission): void
    {
        if ($commission->status !== SellerCommissionStatus::Earned) {
            throw ValidationException::withMessages([
                'commission' => 'Commission is not eligible for payout.',
            ]);
        }

        $commission->loadMissing('sellerOrder.order');

        $sellerOrder = $commission->sellerOrder;

        if ($sellerOrder === null || $sellerOrder->status !== SellerOrderStatus::Delivered) {
            throw ValidationException::withMessages([
                'commission' => 'Seller order must be delivered before payout.',
            ]);
        }

        $order = $sellerOrder->order;

        if ($order === null || $order->payment_status->value !== 'paid') {
            throw ValidationException::withMessages([
                'commission' => 'Parent order must be paid before payout.',
            ]);
        }

        $refundWindowDays = $this->commerceSettings->marketplaceRefundWindowDays();

        if ($refundWindowDays > 0 && $sellerOrder->fulfilled_at !== null) {
            $eligibleAt = $sellerOrder->fulfilled_at->copy()->addDays($refundWindowDays);

            if (now()->lt($eligibleAt)) {
                throw ValidationException::withMessages([
                    'commission' => "Refund window of {$refundWindowDays} day(s) has not elapsed.",
                ]);
            }
        }
    }
}
