<?php

declare(strict_types=1);

namespace App\Services\Tenant\Marketplace;

use App\Enums\Tenant\Marketplace\SellerStatus;
use App\Enums\Tenant\Marketplace\SellerVerificationStatus;
use App\Events\SellerApproved;
use App\Events\SellerRejected;
use App\Events\SellerSuspended;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Seller;
use App\Services\Landlord\Feature\UsageLimiter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Marketplace seller onboarding and administration.
 */
class SellerService
{
    public function __construct(private readonly UsageLimiter $usageLimiter) {}

    /**
     * @param  array{search?: string|null, status?: string|null, verification_status?: string|null, per_page?: int|null}  $params
     * @return LengthAwarePaginator<int, Seller>
     */
    public function list(array $params = []): LengthAwarePaginator
    {
        $query = Seller::query()->with('media')->latest('id');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['verification_status'])) {
            $query->where('verification_status', $params['verification_status']);
        }

        if (! empty($params['search'])) {
            $search = (string) $params['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->paginate($this->perPage($params));
    }

    /**
     * Create a seller in pending verification (inactive until approved).
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Seller
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant && $tenant->activeSubscription() !== null) {
            $this->usageLimiter->assertCanCreate('sellers', $tenant);
        }

        return Seller::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'description' => $data['description'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => SellerStatus::Inactive,
            'verification_status' => SellerVerificationStatus::Pending,
            'commission_type' => $data['commission_type'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? null,
            'commission_fixed_amount' => $data['commission_fixed_amount'] ?? null,
            'seller_group_id' => $data['seller_group_id'] ?? null,
        ]);
    }

    public function show(Seller $seller): Seller
    {
        return $seller->load(['sellerGroup'])->loadCount('offers');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Seller $seller, array $data): Seller
    {
        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $seller->fill($data);
        $seller->save();

        return $seller->fresh() ?? $seller;
    }

    /**
     * Move seller into under_review.
     */
    public function markUnderReview(Seller $seller): Seller
    {
        $seller->verification_status = SellerVerificationStatus::UnderReview;
        $seller->save();

        return $seller->fresh() ?? $seller;
    }

    /**
     * Approve seller for selling and activate the account.
     */
    public function approve(Seller $seller): Seller
    {
        $seller->verification_status = SellerVerificationStatus::Approved;
        $seller->status = SellerStatus::Active;
        $seller->save();

        event(new SellerApproved($seller));

        return $seller->fresh() ?? $seller;
    }

    /**
     * Reject seller verification.
     */
    public function reject(Seller $seller): Seller
    {
        if ($seller->verification_status === SellerVerificationStatus::Approved) {
            throw ValidationException::withMessages([
                'seller' => 'Approved sellers cannot be rejected; suspend them instead.',
            ]);
        }

        $seller->verification_status = SellerVerificationStatus::Rejected;
        $seller->status = SellerStatus::Inactive;
        $seller->save();

        $seller->tokens()->delete();

        event(new SellerRejected($seller));

        return $seller->fresh() ?? $seller;
    }

    /**
     * Suspend an active seller.
     */
    public function suspend(Seller $seller): Seller
    {
        $seller->status = SellerStatus::Suspended;
        $seller->save();

        $seller->tokens()->delete();

        event(new SellerSuspended($seller));

        return $seller->fresh() ?? $seller;
    }

    /**
     * Reactivate a suspended or inactive approved seller.
     */
    public function activate(Seller $seller): Seller
    {
        if ($seller->verification_status !== SellerVerificationStatus::Approved) {
            throw ValidationException::withMessages([
                'seller' => 'Only approved sellers can be activated.',
            ]);
        }

        $seller->status = SellerStatus::Active;
        $seller->save();

        return $seller->fresh() ?? $seller;
    }

    /**
     * @param  array{per_page?: int|null}  $params
     */
    protected function perPage(array $params): int
    {
        return max(1, min((int) ($params['per_page'] ?? 15), 100));
    }
}
