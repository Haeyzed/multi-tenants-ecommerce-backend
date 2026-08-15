<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\GiftCard;
use App\Models\Tenant\User;

/**
 * Authorization for gift cards.
 */
class GiftCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gift_cards.view');
    }

    public function view(User $user, GiftCard $giftCard): bool
    {
        return $user->can('gift_cards.view');
    }

    public function create(User $user): bool
    {
        return $user->can('gift_cards.create');
    }

    public function update(User $user, GiftCard $giftCard): bool
    {
        return $user->can('gift_cards.update');
    }

    public function cancel(User $user, GiftCard $giftCard): bool
    {
        return $user->can('gift_cards.cancel');
    }
}
