<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Contracts\Marketplace\SellerPayoutDriverInterface;
use App\Enums\Tenant\Marketplace\SellerCommissionStatus;
use App\Enums\Tenant\Marketplace\SellerPayoutStatus;
use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerCommission;
use App\Models\Tenant\SellerPayout;
use App\Models\Tenant\User;
use App\Services\Tenant\Accounting\AccountingService;
use App\Services\Tenant\Commerce\CommerceSettingService;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Seller payout batching and processing.
 */
class SellerPayoutService
{
    public function __construct(
        private readonly CommissionService $commissions,
        private readonly CommerceSettingService $commerceSettings,
        private readonly AccountingService $accounting,
        private readonly SellerPayoutDriverInterface $driver,
    ) {}

    /**
     * @param  array{
     *     seller_id: int,
     *     commission_ids: list<int>,
     *     idempotency_key: string
     * }  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, ?User $actor = null): SellerPayout
    {
        if ($actor?->isSellerUser() && (int) $actor->seller_id !== (int) $data['seller_id']) {
            throw ValidationException::withMessages([
                'seller_id' => 'You may only request payouts for your own seller account.',
            ]);
        }

        $existing = SellerPayout::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing !== null) {
            return $existing->load(['seller', 'commissions']);
        }

        return DB::transaction(function () use ($data): SellerPayout {
            $seller = Seller::query()->findOrFail((int) $data['seller_id']);
            $commissionIds = array_values(array_unique(array_map('intval', $data['commission_ids'])));

            if ($commissionIds === []) {
                throw ValidationException::withMessages([
                    'commission_ids' => 'At least one commission is required.',
                ]);
            }

            $commissions = SellerCommission::query()
                ->whereIn('id', $commissionIds)
                ->where('seller_id', $seller->id)
                ->lockForUpdate()
                ->get();

            if ($commissions->count() !== count($commissionIds)) {
                throw ValidationException::withMessages([
                    'commission_ids' => 'One or more commissions are invalid for this seller.',
                ]);
            }

            $amount = '0.00';

            foreach ($commissions as $commission) {
                $this->commissions->assertEligibleForPayout($commission);

                if ($commission->payouts()->exists()) {
                    throw ValidationException::withMessages([
                        'commission_ids' => 'One or more commissions are already included in a payout.',
                    ]);
                }

                $amount = Money::add($amount, (string) $commission->seller_amount);
            }

            if (bccomp($amount, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'commission_ids' => 'Payout amount must be greater than zero.',
                ]);
            }

            $payout = SellerPayout::query()->create([
                'seller_id' => $seller->id,
                'amount' => $amount,
                'currency' => $this->commerceSettings->currencyCode(),
                'status' => SellerPayoutStatus::Pending,
                'idempotency_key' => $data['idempotency_key'],
            ]);

            $payout->commissions()->attach($commissionIds);

            return $this->process($payout);
        });
    }

    /**
     * Execute payout via driver, post accounting, and mark commissions paid.
     */
    public function process(SellerPayout $payout): SellerPayout
    {
        return DB::transaction(function () use ($payout): SellerPayout {
            /** @var SellerPayout $locked */
            $locked = SellerPayout::query()->whereKey($payout->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === SellerPayoutStatus::Paid) {
                return $locked->load(['seller', 'commissions']);
            }

            $locked->status = SellerPayoutStatus::Processing;
            $locked->save();

            $reference = $this->driver->process($locked);

            $locked->status = SellerPayoutStatus::Paid;
            $locked->reference = $reference;
            $locked->paid_at = now();
            $locked->save();

            $locked->loadMissing('commissions');

            foreach ($locked->commissions as $commission) {
                $commission->status = SellerCommissionStatus::Paid;
                $commission->save();
            }

            $this->accounting->postPayout($locked);

            return $locked->fresh(['seller', 'commissions']) ?? $locked;
        });
    }

    /**
     * @param  array{
     *     seller_id?: int|null,
     *     status?: string|null,
     *     per_page?: int|null
     * }  $params
     * @return LengthAwarePaginator<int, SellerPayout>
     */
    public function list(array $params = [], ?User $actor = null): LengthAwarePaginator
    {
        $query = SellerPayout::query()
            ->with(['seller', 'commissions'])
            ->latest('id');

        if ($actor?->isSellerUser()) {
            $query->where('seller_id', $actor->seller_id);
        } elseif (! empty($params['seller_id'])) {
            $query->where('seller_id', $params['seller_id']);
        }

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $query->paginate(max(1, min((int) ($params['per_page'] ?? 15), 100)));
    }

    public function show(SellerPayout $payout): SellerPayout
    {
        return $payout->load(['seller', 'commissions.sellerOrder']);
    }
}
