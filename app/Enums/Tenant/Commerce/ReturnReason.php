<?php

declare(strict_types=1);

namespace App\Enums\Tenant\Commerce;

/**
 * Why a customer is returning an item.
 */
enum ReturnReason: string
{
    case WrongItem = 'wrong_item';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case NotAsDescribed = 'not_as_described';
    case ChangedMind = 'changed_mind';
    case WrongSize = 'wrong_size';
    case MissingItem = 'missing_item';
    case Other = 'other';
}
