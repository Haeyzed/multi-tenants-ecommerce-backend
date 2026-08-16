<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\Seller;
use App\Models\Tenant\SellerOffer;
use App\Models\Tenant\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for seller offers.
 */
class SellerOfferPolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('seller_offers.view');
    }

    public function view(Authenticatable $actor, SellerOffer $offer): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $offer->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('seller_offers.view');
        }

        return false;
    }

    public function create(Authenticatable $actor): bool
    {
        if ($actor instanceof Seller) {
            return true;
        }

        return $actor instanceof User && $actor->can('seller_offers.create');
    }

    public function update(Authenticatable $actor, SellerOffer $offer): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $offer->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('seller_offers.update');
        }

        return false;
    }

    public function delete(Authenticatable $actor, SellerOffer $offer): bool
    {
        if ($actor instanceof Seller) {
            return (int) $actor->id === (int) $offer->seller_id;
        }

        if ($actor instanceof User) {
            return $actor->can('seller_offers.delete');
        }

        return false;
    }
}
