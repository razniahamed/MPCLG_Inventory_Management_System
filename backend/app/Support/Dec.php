<?php

namespace App\Support;

/**
 * Exact decimal arithmetic for quantities (3 dp) and money (2 dp).
 * Never use floats for stock or money (Section 2).
 */
final class Dec
{
    public const QTY = 3;

    public const MONEY = 2;

    public static function n(mixed $value, int $scale = self::QTY): string
    {
        if ($value === null || $value === '') {
            return bcadd('0', '0', $scale);
        }

        return bcadd(is_float($value) ? number_format($value, 6, '.', '') : (string) $value, '0', $scale);
    }

    public static function add(mixed $a, mixed $b, int $scale = self::QTY): string
    {
        return bcadd(self::n($a, $scale + 3), self::n($b, $scale + 3), $scale);
    }

    public static function sub(mixed $a, mixed $b, int $scale = self::QTY): string
    {
        return bcsub(self::n($a, $scale + 3), self::n($b, $scale + 3), $scale);
    }

    public static function mul(mixed $a, mixed $b, int $scale = self::QTY): string
    {
        return bcmul(self::n($a, 6), self::n($b, 6), $scale);
    }

    public static function cmp(mixed $a, mixed $b, int $scale = self::QTY): int
    {
        return bccomp(self::n($a, $scale), self::n($b, $scale), $scale);
    }

    public static function neg(mixed $a, int $scale = self::QTY): string
    {
        return bcmul(self::n($a, $scale), '-1', $scale);
    }

    public static function isZero(mixed $a): bool
    {
        return self::cmp($a, 0) === 0;
    }

    public static function isPositive(mixed $a): bool
    {
        return self::cmp($a, 0) > 0;
    }

    public static function min(mixed $a, mixed $b): string
    {
        return self::cmp($a, $b) <= 0 ? self::n($a) : self::n($b);
    }

    /** @param  iterable<mixed>  $values */
    public static function sum(iterable $values, int $scale = self::QTY): string
    {
        $total = self::n(0, $scale);
        foreach ($values as $v) {
            $total = self::add($total, $v, $scale);
        }

        return $total;
    }
}
