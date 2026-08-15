<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Seller;
use App\Models\Tenant\User;

/**
 * Authorization for marketplace sellers.
 */
class SellerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sellers.view');
    }

    public function view(User $user, Seller $seller): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $seller->id;
        }

        return $user->can('sellers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sellers.create') && ! $user->isSellerUser();
    }

    public function update(User $user, Seller $seller): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $seller->id;
        }

        return $user->can('sellers.update');
    }

    public function approve(User $user, Seller $seller): bool
    {
        return $user->can('sellers.approve') && ! $user->isSellerUser();
    }

    public function reject(User $user, Seller $seller): bool
    {
        return $user->can('sellers.reject') && ! $user->isSellerUser();
    }

    public function suspend(User $user, Seller $seller): bool
    {
        return $user->can('sellers.suspend') && ! $user->isSellerUser();
    }

    public function activate(User $user, Seller $seller): bool
    {
        return $user->can('sellers.update') && ! $user->isSellerUser();
    }
}
