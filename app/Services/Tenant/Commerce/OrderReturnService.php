<?php

declare(strict_types=1);

namespace App\Services\Tenant\Commerce;

use App\Enums\Tenant\Catalog\InventoryMovementType;
use App\Enums\Tenant\Commerce\OrderPaymentStatus;
use App\Enums\Tenant\Commerce\OrderStatus;
use App\Enums\Tenant\Commerce\ReturnInspectionStatus;
use App\Enums\Tenant\Commerce\ReturnItemCondition;
use App\Enums\Tenant\Commerce\ReturnReason;
use App\Enums\Tenant\Commerce\ReturnStatus;
use App\Enums\Tenant\Commerce\ShipmentStatus;
use App\Events\OrderReturnApproved;
use App\Events\OrderReturnCompleted;
use App\Events\OrderReturnReceived;
use App\Events\OrderReturnRejected;
use App\Events\OrderReturnRequested;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\OrderReturn;
use App\Models\Tenant\OrderReturnItem;
use App\Models\Tenant\Seller;
use App\Services\Tenant\Inventory\InventoryService;
use App\Support\Money;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer and staff return merchandise authorization workflow.
 */
class OrderReturnService
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceSettingService  $commerceSettings
     * @param  OrderReturnTransitionService  $transitions
     * @param  RefundService  $refunds
     * @param  InventoryService  $inventory
     */
    public function __construct(
        private readonly CommerceSettingService $commerceSettings,
        private readonly OrderReturnTransitionService $transitions,
        private readonly RefundService $refunds,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Retrieve a paginated list of resources.
     *
     * @param  array{status?: string|null, order_id?: int|null, seller_id?: int|null, per_page?: int|null}  $params
     * @param  ?Authenticatable  $actor
     * @return LengthAwarePaginator<int, OrderReturn>
     */
    public function list(array $params = [], ?Authenticatable $actor = null): LengthAwarePaginator
    {
        $query = OrderReturn::query()
            ->with(['order', 'customer', 'items.orderItem', 'seller'])
            ->latest('id');

        if ($actor instanceof Seller) {
            $query->where('seller_id', $actor->id);
        } elseif (! empty($params['seller_id'])) {
            $query->where('seller_id', (int) $params['seller_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['order_id'])) {
            $query->where('order_id', (int) $params['order_id']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * List for customer.
     *
     * @param  Customer  $customer
     * @param  array  $params
     * @return LengthAwarePaginator<int, OrderReturn>
     */
    public function listForCustomer(Customer $customer, array $params = []): LengthAwarePaginator
    {
        return OrderReturn::query()
            ->with(['order', 'items.orderItem'])
            ->where('customer_id', $customer->id)
            ->when($params['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    /**
     * Retrieve a single resource.
     *
     * @param  OrderReturn  $return
     * @return OrderReturn
     */
    public function show(OrderReturn $return): OrderReturn
    {
        return $return->load(['order.items', 'customer', 'items.orderItem', 'refund', 'seller']);
    }

    /**
     * Customer creates a return request for eligible delivered/fulfilled order lines.
     *
     * @param  Customer  $customer
     * @param  Order  $order
     * @param  array{
     *     items: list<array{order_item_id: int, quantity: int, reason?: string|null}>,
     *     reason?: string|null,
     *     customer_note?: string|null
     * }  $data
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function request(Customer $customer, Order $order, array $data): OrderReturn
    {
        $this->assertCustomerOwnsOrder($customer, $order);
        $this->assertOrderEligibleForReturn($order);

        $items = $data['items'] ?? [];
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one return item is required.',
            ]);
        }

        return DB::transaction(function () use ($customer, $order, $data, $items): OrderReturn {
            Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            $sellerIds = [];
            $prepared = [];

            foreach ($items as $index => $line) {
                $orderItem = OrderItem::query()
                    ->whereKey((int) $line['order_item_id'])
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                if ($orderItem === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.order_item_id" => 'Order item does not belong to this order.',
                    ]);
                }

                $quantity = (int) $line['quantity'];
                $available = $this->returnableQuantity($orderItem);

                if ($quantity < 1 || $quantity > $available) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Only {$available} unit(s) may be returned for this line.",
                    ]);
                }

                $lineRefund = $this->lineRefundAmount($orderItem, $quantity);

                $prepared[] = [
                    'order_item' => $orderItem,
                    'quantity' => $quantity,
                    'reason' => $line['reason'] ?? $data['reason'] ?? null,
                    'refund_amount' => $lineRefund,
                ];

                if ($orderItem->seller_id !== null) {
                    $sellerIds[(int) $orderItem->seller_id] = true;
                }
            }

            if (count($sellerIds) > 1) {
                throw ValidationException::withMessages([
                    'items' => 'Marketplace returns must target items from a single seller per request.',
                ]);
            }

            $sellerId = array_key_first($sellerIds);

            $return = OrderReturn::query()->create([
                'return_number' => $this->generateReturnNumber(),
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'seller_id' => $sellerId !== null ? (int) $sellerId : null,
                'status' => ReturnStatus::Requested,
                'reason' => $data['reason'] ?? ReturnReason::Other->value,
                'customer_note' => $data['customer_note'] ?? null,
                'requested_at' => Carbon::now(),
            ]);

            foreach ($prepared as $row) {
                /** @var OrderItem $orderItem */
                $orderItem = $row['order_item'];
                OrderReturnItem::query()->create([
                    'order_return_id' => $return->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $row['quantity'],
                    'reason' => $row['reason'],
                    'inspection_status' => ReturnInspectionStatus::Pending,
                    'refund_amount' => $row['refund_amount'],
                ]);
            }

            event(new OrderReturnRequested($return));

            return $this->show($return);
        });
    }

    /**
     * Mark under review.
     *
     * @param  OrderReturn  $return
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function markUnderReview(OrderReturn $return): OrderReturn
    {
        return $this->transitions->transition($return, ReturnStatus::UnderReview);
    }

    /**
     * Approve.
     *
     * @param  OrderReturn  $return
     * @param  ?string  $adminNote
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function approve(OrderReturn $return, ?string $adminNote = null): OrderReturn
    {
        if ($adminNote !== null) {
            $return->admin_note = $adminNote;
            $return->save();
        }

        $return = $this->transitions->transition($return, ReturnStatus::Approved);
        $return = $this->transitions->transition($return, ReturnStatus::AwaitingReturn);

        event(new OrderReturnApproved($return));

        return $return;
    }

    /**
     * Reject.
     *
     * @param  OrderReturn  $return
     * @param  ?string  $adminNote
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function reject(OrderReturn $return, ?string $adminNote = null): OrderReturn
    {
        if ($adminNote !== null) {
            $return->admin_note = $adminNote;
            $return->save();
        }

        if ($return->status === ReturnStatus::Requested) {
            $return = $this->transitions->transition($return, ReturnStatus::UnderReview);
        }

        $return = $this->transitions->transition($return, ReturnStatus::Rejected);
        event(new OrderReturnRejected($return));

        return $return;
    }

    /**
     * Mark received.
     *
     * @param  OrderReturn  $return
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function markReceived(OrderReturn $return): OrderReturn
    {
        if (in_array($return->status, [ReturnStatus::AwaitingReturn, ReturnStatus::InTransit], true)) {
            if ($return->status === ReturnStatus::AwaitingReturn) {
                // allow direct receive
            }
        }

        if ($return->status === ReturnStatus::AwaitingReturn || $return->status === ReturnStatus::InTransit) {
            $return = $this->transitions->transition($return, ReturnStatus::Received);
            event(new OrderReturnReceived($return));

            return $return;
        }

        throw ValidationException::withMessages([
            'status' => 'Return cannot be marked received from the current status.',
        ]);
    }

    /**
     * Start inspection.
     *
     * @param  OrderReturn  $return
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function startInspection(OrderReturn $return): OrderReturn
    {
        return $this->transitions->transition($return, ReturnStatus::Inspecting);
    }

    /**
     * Inspect a return line and optionally restock accepted sellable units.
     *
     * @param  OrderReturnItem  $item
     * @param  User  $inspector
     * @param  array{
     *     inspection_status: string,
     *     condition?: string|null,
     *     inspection_note?: string|null,
     *     restock?: bool|null
     * }  $data
     * @return OrderReturnItem
     *
     * @throws ValidationException
     */
    public function inspectItem(OrderReturnItem $item, User $inspector, array $data): OrderReturnItem
    {
        $item->loadMissing('orderReturn');
        $return = $item->orderReturn;

        if ($return === null || $return->status !== ReturnStatus::Inspecting) {
            throw ValidationException::withMessages([
                'return' => 'Return must be in inspecting status.',
            ]);
        }

        $status = ReturnInspectionStatus::from((string) $data['inspection_status']);
        $item->inspection_status = $status;
        $item->condition = isset($data['condition'])
            ? ReturnItemCondition::from((string) $data['condition'])
            : $item->condition;
        $item->inspection_note = $data['inspection_note'] ?? null;
        $item->inspected_by = $inspector->id;
        $item->inspected_at = Carbon::now();
        $item->save();

        $shouldRestock = (bool) ($data['restock'] ?? false);
        if (
            $shouldRestock
            && $status === ReturnInspectionStatus::Accepted
            && ! $item->restocked
            && in_array($item->condition, [ReturnItemCondition::New, ReturnItemCondition::Opened], true)
        ) {
            $this->restockItem($item);
        }

        return $item->fresh(['orderItem', 'inspector']) ?? $item;
    }

    /**
     * Approve return for refund after all lines inspected.
     *
     * @param  OrderReturn  $return
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function approveForRefund(OrderReturn $return): OrderReturn
    {
        $return->loadMissing('items');

        if ($return->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Return has no items.',
            ]);
        }

        $pending = $return->items->contains(
            fn (OrderReturnItem $item): bool => $item->inspection_status === ReturnInspectionStatus::Pending
        );

        if ($pending) {
            throw ValidationException::withMessages([
                'items' => 'All return items must be inspected first.',
            ]);
        }

        $accepted = $return->items->contains(
            fn (OrderReturnItem $item): bool => $item->inspection_status === ReturnInspectionStatus::Accepted
        );

        if (! $accepted) {
            return $this->transitions->transition($return, ReturnStatus::Rejected);
        }

        return $this->transitions->transition($return, ReturnStatus::ApprovedForRefund);
    }

    /**
     * Process gateway refund for accepted return lines via RefundService.
     *
     * @param  OrderReturn  $return
     * @return OrderReturn
     *
     * @throws ValidationException
     */
    public function processRefund(OrderReturn $return): OrderReturn
    {
        $return = $this->transitions->transition($return, ReturnStatus::RefundProcessing);
        $return->loadMissing(['order.payments', 'items']);

        $amount = '0.00';
        foreach ($return->items as $item) {
            if ($item->inspection_status === ReturnInspectionStatus::Accepted) {
                $amount = Money::add($amount, (string) $item->refund_amount);
            }
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'No accepted items to refund.',
            ]);
        }

        if ($return->order === null) {
            throw ValidationException::withMessages([
                'order' => 'Return order is missing.',
            ]);
        }

        $refund = $this->refunds->refundAllocated($return->order, $amount, [
            'reason' => 'Return '.$return->return_number,
        ]);

        $return->refund_id = $refund->id;
        $return->save();

        $return = $this->transitions->transition($return->fresh() ?? $return, ReturnStatus::Completed);
        event(new OrderReturnCompleted($return));

        return $this->show($return);
    }

    /**
     * Quantity still available to return for an order line.
     *
     * @param  OrderItem  $orderItem
     * @return int
     */
    public function returnableQuantity(OrderItem $orderItem): int
    {
        $returned = (int) OrderReturnItem::query()
            ->where('order_item_id', $orderItem->id)
            ->whereHas('orderReturn', function ($q): void {
                $q->whereNotIn('status', [
                    ReturnStatus::Rejected->value,
                    ReturnStatus::Cancelled->value,
                ]);
            })
            ->sum('quantity');

        return max(0, $orderItem->quantity - $returned);
    }

    /**
     * Assert customer owns order.
     *
     * @param  Customer  $customer
     * @param  Order  $order
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertCustomerOwnsOrder(Customer $customer, Order $order): void
    {
        if ((int) $order->customer_id !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'order' => 'Order does not belong to this customer.',
            ]);
        }
    }

    /**
     * Assert order eligible for return.
     *
     * @param  Order  $order
     * @return void
     *
     * @throws ValidationException
     */
    protected function assertOrderEligibleForReturn(Order $order): void
    {
        if (! in_array($order->payment_status, [
            OrderPaymentStatus::Paid,
            OrderPaymentStatus::PartiallyRefunded,
        ], true)) {
            throw ValidationException::withMessages([
                'order' => 'Only paid or partially refunded orders can be returned.',
            ]);
        }

        $eligibleStatus = in_array($order->status, [
            OrderStatus::Fulfilled,
            OrderStatus::Completed,
        ], true);

        $delivered = $order->shipments()
            ->where('status', ShipmentStatus::Delivered)
            ->exists();

        if (! $eligibleStatus && ! $delivered) {
            throw ValidationException::withMessages([
                'order' => 'Order must be delivered or fulfilled before requesting a return.',
            ]);
        }

        $windowDays = $this->commerceSettings->returnWindowDays();
        $anchor = $order->shipments()
            ->where('status', ShipmentStatus::Delivered)
            ->latest('delivered_at')
            ->value('delivered_at')
            ?? $order->confirmed_at
            ?? $order->placed_at;

        if ($anchor !== null && Carbon::parse($anchor)->addDays($windowDays)->isPast()) {
            throw ValidationException::withMessages([
                'order' => "The {$windowDays}-day return window has expired.",
            ]);
        }
    }

    /**
     * Proportional share of the line's net total (after discount, including tax).
     *
     * @param  OrderItem  $orderItem
     * @param  int  $quantity
     * @return string
     */
    protected function lineRefundAmount(OrderItem $orderItem, int $quantity): string
    {
        if ($orderItem->quantity <= 0 || $quantity <= 0) {
            return '0.00';
        }

        $share = Money::mul(
            (string) $orderItem->total,
            bcdiv((string) $quantity, (string) $orderItem->quantity, 8),
        );

        return Money::add($share, '0');
    }

    /**
     * Restock accepted return quantity onto the original inventory row when present.
     *
     * @param  OrderReturnItem  $item
     * @return void
     */
    protected function restockItem(OrderReturnItem $item): void
    {
        $item->loadMissing('orderItem.inventory');
        $orderItem = $item->orderItem;

        if ($orderItem?->inventory === null) {
            return;
        }

        $this->inventory->increase(
            $orderItem->inventory,
            $item->quantity,
            InventoryMovementType::SaleReturn,
            'Return restock '.$item->order_return_id,
            null,
            null,
            $item->orderReturn,
        );

        $item->restocked = true;
        $item->save();
    }

    /**
     * Generate return number.
     *
     * @return string
     */
    protected function generateReturnNumber(): string
    {
        $prefix = 'RET-'.now()->format('Ymd').'-';
        $latest = OrderReturn::query()
            ->where('return_number', 'like', $prefix.'%')
            ->orderByDesc('return_number')
            ->value('return_number');

        $sequence = 1;
        if (is_string($latest) && preg_match('/(\d{6})$/', $latest, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
