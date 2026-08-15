<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decimal money arithmetic helpers using bcmath at scale 2.
 */
final class Money
{
    private const int SCALE = 2;

    /**
     * Prevent instantiation.
     */
    private function __construct() {}

    /**
     * Add two decimal amounts.
     */
    public static function add(string $left, string $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), self::SCALE);
    }

    /**
     * Subtract right from left.
     */
    public static function sub(string $left, string $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), self::SCALE);
    }

    /**
     * Multiply two decimal amounts.
     */
    public static function mul(string $left, string $right): string
    {
        return bcmul(self::normalize($left), self::normalize($right), self::SCALE);
    }

    /**
     * Calculate a percentage of an amount (e.g. percent('100.00', '7.50') => '7.50').
     */
    public static function percent(string $amount, string $rate): string
    {
        $product = bcmul(self::normalize($amount), self::normalize($rate), 4);

        return bcdiv($product, '100', self::SCALE);
    }

    private static function normalize(string $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }
}
