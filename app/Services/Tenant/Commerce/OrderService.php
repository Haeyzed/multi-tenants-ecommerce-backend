<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Commerce\OrderStatus;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Customer and admin order queries and cancellation.
 */
class OrderService
{
    public function __construct(
        private readonly OrderTransitionService $transitions,
    ) {}

    /**
     * Paginate orders for a customer.
     *
     * @param  array{status?: string|null, sort?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Order>
     */
    public function customerList(Customer $customer, array $params = []): LengthAwarePaginator
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->with(['items', 'shippingMethod'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Show a customer-owned order.
     */
    public function customerShow(Customer $customer, Order $order): Order
    {
        $this->assertCustomerOwnership($customer, $order);

        return $order->load(['items', 'shippingMethod', 'customer']);
    }

    /**
     * Paginate orders for admin with filters.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     payment_status?: string|null,
     *     customer_id?: int|null,
     *     sort?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, Order>
     */
    public function adminList(array $params = []): LengthAwarePaginator
    {
        return Order::query()
            ->with(['customer', 'items', 'shippingMethod'])
            ->filter($params)
            ->applySort($params['sort'] ?? null)
            ->paginate($this->perPage($params));
    }

    /**
     * Show an order for admin.
     */
    public function adminShow(Order $order): Order
    {
        return $order->load(['customer', 'items', 'shippingMethod', 'payments', 'shipments']);
    }

    /**
     * Cancel an order via status transition.
     */
    public function cancel(Order $order): Order
    {
        return $this->transitions->transition($order, OrderStatus::Cancelled);
    }

    protected function assertCustomerOwnership(Customer $customer, Order $order): void
    {
        if ((int) $order->customer_id !== (int) $customer->id) {
            throw new AccessDeniedHttpException('Order does not belong to this customer.');
        }
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
