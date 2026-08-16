<?php

declare(strict_types=1);

namespace App\Services\Tenant\Pos;

use App\Enums\Tenant\Pos\SalesChannel;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\PosSession;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregate POS sales reports.
 */
class PosReportService
{
    /**
     * @return array{
     *     session_id: int,
     *     status: string,
     *     opening_cash: string,
     *     expected_cash: string,
     *     actual_cash: string|null,
     *     cash_difference: string|null,
     *     sales_count: int,
     *     sales_total: string,
     *     cash_movements: list<array{type: string, total: string}>
     * }
     */
    public function sessionSummary(PosSession $session, PosSessionService $sessions): array
    {
        $sales = Order::query()
            ->where('pos_session_id', $session->id)
            ->where('sales_channel', SalesChannel::Pos)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(grand_total), 0) as sales_total')
            ->first();

        $movements = $session->cashMovements()
            ->selectRaw('type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('type')
            ->get()
            ->map(fn ($row): array => [
                'type' => is_object($row->type) && property_exists($row->type, 'value')
                    ? (string) $row->type->value
                    : (string) $row->type,
                'total' => (string) $row->total,
            ])
            ->values()
            ->all();

        return [
            'session_id' => $session->id,
            'status' => $session->status->value,
            'opening_cash' => (string) $session->opening_cash,
            'expected_cash' => $session->expected_cash !== null
                ? (string) $session->expected_cash
                : $sessions->expectedCash($session),
            'actual_cash' => $session->actual_cash !== null ? (string) $session->actual_cash : null,
            'cash_difference' => $session->cash_difference !== null ? (string) $session->cash_difference : null,
            'sales_count' => (int) ($sales->sales_count ?? 0),
            'sales_total' => (string) ($sales->sales_total ?? '0.00'),
            'cash_movements' => $movements,
        ];
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return list<array{pos_terminal_id: int, sales_count: int, sales_total: string}>
     */
    public function salesByTerminal(array $params = []): array
    {
        return $this->posOrdersQuery($params)
            ->selectRaw('pos_terminal_id, COUNT(*) as sales_count, COALESCE(SUM(grand_total), 0) as sales_total')
            ->whereNotNull('pos_terminal_id')
            ->groupBy('pos_terminal_id')
            ->orderByDesc('sales_total')
            ->get()
            ->map(fn ($row): array => [
                'pos_terminal_id' => (int) $row->pos_terminal_id,
                'sales_count' => (int) $row->sales_count,
                'sales_total' => (string) $row->sales_total,
            ])
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return list<array{user_id: int, sales_count: int, sales_total: string}>
     */
    public function salesByCashier(array $params = []): array
    {
        return Order::query()
            ->from('orders')
            ->join('pos_sessions', 'pos_sessions.id', '=', 'orders.pos_session_id')
            ->where('orders.sales_channel', SalesChannel::Pos->value)
            ->when($params['from'] ?? null, fn ($query, string $from) => $query->where('orders.placed_at', '>=', $from))
            ->when($params['to'] ?? null, fn ($query, string $to) => $query->where('orders.placed_at', '<=', $to))
            ->selectRaw('pos_sessions.user_id as user_id, COUNT(orders.id) as sales_count, COALESCE(SUM(orders.grand_total), 0) as sales_total')
            ->groupBy('pos_sessions.user_id')
            ->orderByDesc('sales_total')
            ->get()
            ->map(fn ($row): array => [
                'user_id' => (int) $row->user_id,
                'sales_count' => (int) $row->sales_count,
                'sales_total' => (string) $row->sales_total,
            ])
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return list<array{gateway: string, payments_count: int, payments_total: string}>
     */
    public function paymentMethodTotals(array $params = []): array
    {
        return OrderPayment::query()
            ->join('orders', 'orders.id', '=', 'order_payments.order_id')
            ->where('orders.sales_channel', SalesChannel::Pos->value)
            ->when($params['from'] ?? null, fn ($query, string $from) => $query->where('orders.placed_at', '>=', $from))
            ->when($params['to'] ?? null, fn ($query, string $to) => $query->where('orders.placed_at', '<=', $to))
            ->selectRaw('order_payments.gateway, COUNT(order_payments.id) as payments_count, COALESCE(SUM(order_payments.amount), 0) as payments_total')
            ->groupBy('order_payments.gateway')
            ->orderByDesc('payments_total')
            ->get()
            ->map(fn ($row): array => [
                'gateway' => (string) $row->gateway,
                'payments_count' => (int) $row->payments_count,
                'payments_total' => (string) $row->payments_total,
            ])
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $params
     * @return Builder<Order>
     */
    protected function posOrdersQuery(array $params = [])
    {
        return Order::query()
            ->where('sales_channel', SalesChannel::Pos)
            ->when($params['from'] ?? null, fn ($query, string $from) => $query->where('placed_at', '>=', $from))
            ->when($params['to'] ?? null, fn ($query, string $to) => $query->where('placed_at', '<=', $to));
    }
}
