<?php

namespace App\Support;

/**
 * Kurumsal para aritmetiği — bcmath ile 2 ondalık hassasiyet (kuruş).
 */
final class Money
{
    public const SCALE = 2;

    /** @param  string|int|float|null  $amount */
    public static function normalize(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        if (! is_numeric($amount)) {
            return '0.00';
        }

        $rounded = round((float) $amount, self::SCALE, PHP_ROUND_HALF_UP);

        return bcadd((string) $rounded, '0', self::SCALE);
    }

    public static function add(string|int|float $a, string|int|float $b): string
    {
        return bcadd(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function sub(string|int|float $a, string|int|float $b): string
    {
        return bcsub(self::normalize($a), self::normalize($b), self::SCALE);
    }

    public static function mul(string|int|float $amount, int|string|float $quantity): string
    {
        return bcmul(self::normalize($amount), (string) $quantity, self::SCALE);
    }

    /**
     * @param  array<int|string, string|int|float|null>  $amounts
     */
    public static function sum(array $amounts): string
    {
        $total = '0.00';

        foreach ($amounts as $amount) {
            $total = self::add($total, $amount ?? '0');
        }

        return $total;
    }

    /** KDV / servis bedeli: tutar × oran% (ör. 20 → %20 KDV). */
    public static function percentage(string|int|float $amount, string|int|float $ratePercent): string
    {
        $product = bcmul(
            self::normalize($amount),
            self::normalize($ratePercent),
            self::SCALE + 2,
        );

        return bcdiv($product, '100', self::SCALE);
    }

    public static function max(string|int|float $a, string|int|float $b): string
    {
        $left = self::normalize($a);
        $right = self::normalize($b);

        return bccomp($left, $right, self::SCALE) >= 0 ? $left : $right;
    }

    /** Yalnızca sunum/API katmanında — hesaplamalarda kullanmayın. */
    public static function toFloat(string|int|float|null $amount): float
    {
        return (float) self::normalize($amount);
    }
}
