<?php

declare(strict_types=1);

namespace App\Services\Tenant\Analytics;

use App\Enums\Tenant\Analytics\ReportInterval;
use App\Enums\Tenant\Commerce\OrderPaymentRecordStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Commerce\RefundStatus;
use App\Models\Tenant\CouponUsage;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\Refund;
use App\Models\Tenant\SellerCommission;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce reporting aggregates.
 *
 * Every metric is computed with SQL aggregates so report size stays independent of
 * the number of orders, items, or payments in the window.
 */
class CommerceReportService
{
    /**
     * Headline sales figures for a period.
     *
     * @param  DateRange  $range
     * @return array{
     */
    public function salesSummary(DateRange $range): array
    {
        [$from, $to] = $range->bounds();

        /** @var object{orders_count: int|string, gross: string|null, net: string|null, discount_total: string|null, tax_total: string|null, shipping_total: string|null}|null $row */
        $row = $this->countableOrders()
            ->whereBetween('placed_at', [$from, $to])
            ->toBase()
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as net')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(tax_total), 0) as tax_total')
            ->selectRaw('COALESCE(SUM(shipping_total), 0) as shipping_total')
            ->first();

        $ordersCount = (int) ($row->orders_count ?? 0);
        $net = $this->money($row->net ?? 0);

        $itemsSold = (int) OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->sum('order_items.quantity');

        return [
            'orders_count' => $ordersCount,
            'gross' => $this->money($row->gross ?? 0),
            'net' => $net,
            'aov' => Money::div($net, (string) max($ordersCount, 0)),
            'discount_total' => $this->money($row->discount_total ?? 0),
            'tax_total' => $this->money($row->tax_total ?? 0),
            'shipping_total' => $this->money($row->shipping_total ?? 0),
            'refund_total' => $this->refundTotal($range),
            'items_sold' => $itemsSold,
        ];
    }

    /**
     * Sales grouped into day, week, or month buckets.
     *
     * @param  DateRange  $range
     * @param  ReportInterval  $interval
     * @return list<array{bucket: string, orders_count: int, gross: string, net: string, discount_total: string}>
     */
    public function salesBreakdown(DateRange $range, ReportInterval $interval = ReportInterval::Day): array
    {
        [$from, $to] = $range->bounds();
        $expression = $this->bucketExpression('orders.placed_at', $interval);

        return $this->countableOrders()
            ->whereBetween('placed_at', [$from, $to])
            ->toBase()
            ->selectRaw("{$expression} as bucket")
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as net')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_total')
            ->groupBy(DB::raw($expression))
            ->orderBy(DB::raw($expression))
            ->get()
            ->map(fn (object $row): array => [
                'bucket' => (string) $row->bucket,
                'orders_count' => (int) $row->orders_count,
                'gross' => $this->money($row->gross),
                'net' => $this->money($row->net),
                'discount_total' => $this->money($row->discount_total),
            ])
            ->all();
    }

    /**
     * Customer acquisition and retention figures for a period.
     *
     * @param  DateRange  $range
     * @return array{
     */
    public function customerMetrics(DateRange $range): array
    {
        [$from, $to] = $range->bounds();

        $newCustomers = (int) Customer::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $activeCustomers = (int) $this->countableOrders()
            ->whereBetween('placed_at', [$from, $to])
            ->distinct()
            ->count('customer_id');

        $returningCustomers = (int) Customer::query()
            ->whereHas('orders', function (Builder $query) use ($from, $to): void {
                $query->where('status', '!=', OrderStatus::Cancelled->value)
                    ->whereBetween('placed_at', [$from, $to]);
            })
            ->whereHas('orders', function (Builder $query) use ($from): void {
                $query->where('status', '!=', OrderStatus::Cancelled->value)
                    ->where('placed_at', '<', $from);
            })
            ->count();

        $summary = $this->salesSummary($range);

        return [
            'total_customers' => (int) Customer::query()->count(),
            'new_customers' => $newCustomers,
            'active_customers' => $activeCustomers,
            'returning_customers' => $returningCustomers,
            'average_order_value' => $summary['aov'],
            'average_customer_value' => Money::div($summary['net'], (string) $activeCustomers),
        ];
    }

    /**
     * Best selling products in a period.
     *
     * @param  DateRange  $range
     * @param  int  $limit
     * @return list<array{product_id: int|null, product_name: string, quantity_sold: int, revenue: string, orders_count: int}>
     */
    public function productMetrics(DateRange $range, int $limit = 10): array
    {
        [$from, $to] = $range->bounds();

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->toBase()
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id as product_id')
            ->selectRaw('order_items.product_name as product_name')
            ->selectRaw('SUM(order_items.quantity) as quantity_sold')
            ->selectRaw('COALESCE(SUM(order_items.total), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (object $row): array => [
                'product_id' => $row->product_id === null ? null : (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'quantity_sold' => (int) $row->quantity_sold,
                'revenue' => $this->money($row->revenue),
                'orders_count' => (int) $row->orders_count,
            ])
            ->all();
    }

    /**
     * Current stock position across all warehouses.
     *
     * @return array{
     */
    public function inventoryMetrics(): array
    {
        /** @var object{tracked_items: int|string, quantity_on_hand: int|string|null, quantity_reserved: int|string|null}|null $totals */
        $totals = Inventory::query()
            ->toBase()
            ->selectRaw('COUNT(*) as tracked_items')
            ->selectRaw('COALESCE(SUM(quantity), 0) as quantity_on_hand')
            ->selectRaw('COALESCE(SUM(reserved_quantity), 0) as quantity_reserved')
            ->first();

        $onHand = (int) ($totals->quantity_on_hand ?? 0);
        $reserved = (int) ($totals->quantity_reserved ?? 0);

        $outOfStock = (int) Inventory::query()
            ->whereRaw('(quantity - reserved_quantity) <= 0')
            ->count();

        $lowStock = (int) Inventory::query()
            ->whereNotNull('reorder_level')
            ->whereRaw('(quantity - reserved_quantity) > 0')
            ->whereRaw('(quantity - reserved_quantity) <= reorder_level')
            ->count();

        return [
            'tracked_items' => (int) ($totals->tracked_items ?? 0),
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'quantity_available' => $onHand - $reserved,
            'low_stock_count' => $lowStock,
            'out_of_stock_count' => $outOfStock,
        ];
    }

    /**
     * Marketplace commission aggregates, optionally scoped to one seller.
     *
     * @param  DateRange  $range
     * @param  ?int  $sellerId
     * @return array{
     */
    public function marketplaceMetrics(DateRange $range, ?int $sellerId = null): ?array
    {
        if (! Schema::hasTable('sellers') || ! Schema::hasTable('seller_commissions')) {
            return null;
        }

        [$from, $to] = $range->bounds();

        /** @var object{sellers_count: int|string, orders_count: int|string, gross_sales: string|null, commission_total: string|null, seller_earnings: string|null}|null $row */
        $row = SellerCommission::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($sellerId !== null, fn (Builder $query) => $query->where('seller_id', $sellerId))
            ->toBase()
            ->selectRaw('COUNT(DISTINCT seller_id) as sellers_count')
            ->selectRaw('COUNT(DISTINCT order_id) as orders_count')
            ->selectRaw('COALESCE(SUM(order_subtotal), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(commission_amount), 0) as commission_total')
            ->selectRaw('COALESCE(SUM(seller_amount), 0) as seller_earnings')
            ->first();

        return [
            'sellers_count' => (int) ($row->sellers_count ?? 0),
            'orders_count' => (int) ($row->orders_count ?? 0),
            'gross_sales' => $this->money($row->gross_sales ?? 0),
            'commission_total' => $this->money($row->commission_total ?? 0),
            'seller_earnings' => $this->money($row->seller_earnings ?? 0),
        ];
    }

    /**
     * Marketplace figures restricted to a single seller.
     *
     * @param  DateRange  $range
     * @param  int  $sellerId
     * @return array{
     */
    public function sellerMetrics(DateRange $range, int $sellerId): ?array
    {
        return $this->marketplaceMetrics($range, $sellerId);
    }

    /**
     * Coupon redemption aggregates for a period.
     *
     * @param  DateRange  $range
     * @return array{
     */
    public function couponMetrics(DateRange $range): array
    {
        [$from, $to] = $range->bounds();

        /** @var object{redemptions: int|string, discount_total: string|null, unique_customers: int|string}|null $row */
        $row = CouponUsage::query()
            ->whereBetween('created_at', [$from, $to])
            ->toBase()
            ->selectRaw('COUNT(*) as redemptions')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_total')
            ->selectRaw('COUNT(DISTINCT customer_id) as unique_customers')
            ->first();

        $topCoupons = CouponUsage::query()
            ->whereBetween('created_at', [$from, $to])
            ->toBase()
            ->groupBy('coupon_id')
            ->selectRaw('coupon_id')
            ->selectRaw('COUNT(*) as redemptions')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_total')
            ->orderByRaw('COALESCE(SUM(discount_amount), 0) DESC')
            ->limit(10)
            ->get()
            ->map(fn (object $item): array => [
                'coupon_id' => (int) $item->coupon_id,
                'redemptions' => (int) $item->redemptions,
                'discount_total' => $this->money($item->discount_total),
            ])
            ->all();

        return [
            'redemptions' => (int) ($row->redemptions ?? 0),
            'discount_total' => $this->money($row->discount_total ?? 0),
            'unique_customers' => (int) ($row->unique_customers ?? 0),
            'top_coupons' => $topCoupons,
        ];
    }

    /**
     * Payment capture aggregates for a period.
     *
     * @param  DateRange  $range
     * @return array{
     */
    public function paymentMetrics(DateRange $range): array
    {
        [$from, $to] = $range->bounds();

        $base = fn (): Builder => OrderPayment::query()->whereBetween('created_at', [$from, $to]);

        /** @var object{captured_count: int|string, captured_total: string|null}|null $captured */
        $captured = $base()
            ->where('status', OrderPaymentRecordStatus::Successful->value)
            ->toBase()
            ->selectRaw('COUNT(*) as captured_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as captured_total')
            ->first();

        $byGateway = $base()
            ->where('status', OrderPaymentRecordStatus::Successful->value)
            ->toBase()
            ->groupBy('gateway')
            ->selectRaw('gateway')
            ->selectRaw('COUNT(*) as captured_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as captured_total')
            ->orderByRaw('COALESCE(SUM(amount), 0) DESC')
            ->get()
            ->map(fn (object $row): array => [
                'gateway' => (string) $row->gateway,
                'captured_count' => (int) $row->captured_count,
                'captured_total' => $this->money($row->captured_total),
            ])
            ->all();

        return [
            'captured_total' => $this->money($captured->captured_total ?? 0),
            'captured_count' => (int) ($captured->captured_count ?? 0),
            'failed_count' => (int) $base()->where('status', OrderPaymentRecordStatus::Failed->value)->count(),
            'pending_count' => (int) $base()->where('status', OrderPaymentRecordStatus::Pending->value)->count(),
            'by_gateway' => $byGateway,
        ];
    }

    /**
     * Total completed refunds within a period.
     *
     * @param  DateRange  $range
     * @return string
     */
    protected function refundTotal(DateRange $range): string
    {
        if (! Schema::hasTable('refunds')) {
            return '0.00';
        }

        [$from, $to] = $range->bounds();

        return $this->money(
            Refund::query()
                ->where('status', RefundStatus::Completed->value)
                ->whereBetween('created_at', [$from, $to])
                ->sum('amount'),
        );
    }

    /**
     * Orders that count towards revenue.
     *
     * @return Builder<Order>
     */
    protected function countableOrders(): Builder
    {
        return Order::query()->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * Driver-aware SQL expression bucketing a timestamp column.
     *
     * @param  string  $column
     * @param  ReportInterval  $interval
     * @return string
     */
    protected function bucketExpression(string $column, ReportInterval $interval): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => match ($interval) {
                ReportInterval::Day => "strftime('%Y-%m-%d', {$column})",
                ReportInterval::Week => "strftime('%Y-W%W', {$column})",
                ReportInterval::Month => "strftime('%Y-%m', {$column})",
            },
            'pgsql' => match ($interval) {
                ReportInterval::Day => "to_char({$column}, 'YYYY-MM-DD')",
                ReportInterval::Week => "to_char({$column}, 'IYYY-\"W\"IW')",
                ReportInterval::Month => "to_char({$column}, 'YYYY-MM')",
            },
            default => match ($interval) {
                ReportInterval::Day => "DATE_FORMAT({$column}, '%Y-%m-%d')",
                ReportInterval::Week => "DATE_FORMAT({$column}, '%x-W%v')",
                ReportInterval::Month => "DATE_FORMAT({$column}, '%Y-%m')",
            },
        };
    }

    /**
     * Normalize an aggregate result to a two-decimal money string.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function money(mixed $value): string
    {
        return Money::add((string) ($value ?? '0'), '0.00');
    }
}
