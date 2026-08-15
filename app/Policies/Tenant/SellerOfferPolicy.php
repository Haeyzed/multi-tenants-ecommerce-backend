<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\User;

/**
 * Authorization for seller offers.
 */
class SellerOfferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('seller_offers.view');
    }

    public function view(User $user, SellerOffer $offer): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $offer->seller_id
                && $user->can('seller_offers.view');
        }

        return $user->can('seller_offers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('seller_offers.create');
    }

    public function update(User $user, SellerOffer $offer): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $offer->seller_id
                && $user->can('seller_offers.update');
        }

        return $user->can('seller_offers.update');
    }

    public function delete(User $user, SellerOffer $offer): bool
    {
        if ($user->isSellerUser()) {
            return (int) $user->seller_id === (int) $offer->seller_id
                && $user->can('seller_offers.delete');
        }

        return $user->can('seller_offers.delete');
    }
}
