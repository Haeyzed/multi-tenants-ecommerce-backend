<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Seller;
use App\Models\Tenant\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for marketplace sellers.
 */
class SellerPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return $actor instanceof User && $actor->can('sellers.view');
    }

    public function view(Authenticatable $actor, Seller $seller): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $seller->id;
        }

        if ($actor instanceof User) {
            return $actor->can('sellers.view');
        }

        return false;
    }

    public function create(Authenticatable $actor): bool
    {
        return $actor instanceof User && $actor->can('sellers.create');
    }

    public function update(Authenticatable $actor, Seller $seller): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $seller->id;
        }

        if ($actor instanceof User) {
            return $actor->can('sellers.update');
        }

        return false;
    }

    public function approve(Authenticatable $actor, Seller $seller): bool
    {
        return $actor instanceof User && $actor->can('sellers.approve');
    }

    public function reject(Authenticatable $actor, Seller $seller): bool
    {
        return $actor instanceof User && $actor->can('sellers.reject');
    }

    public function suspend(Authenticatable $actor, Seller $seller): bool
    {
        return $actor instanceof User && $actor->can('sellers.suspend');
    }

    public function activate(Authenticatable $actor, Seller $seller): bool
    {
        return $actor instanceof User && $actor->can('sellers.update');
    }
}
