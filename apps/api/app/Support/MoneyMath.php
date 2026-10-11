<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

final class MoneyMath
{
    private const SCALE = 1000000;

    public static function lineTotal(
        string $quantity,
        int $unitPrice
    ): int {
        if (
            preg_match(
                '/\A\d{1,12}(?:\.\d{1,6})?\z/',
                $quantity
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid quantity precision.'
            );
        }
        if ($unitPrice < 0) {
            throw new InvalidArgumentException(
                'Unit price cannot be negative.'
            );
        }
        [$whole, $decimal] = array_pad(
            explode('.', $quantity, 2),
            2,
            ''
        );
        $whole = (int) $whole;
        $fraction = (int) str_pad(
            $decimal,
            6,
            '0',
            STR_PAD_RIGHT
        );
        if (
            $unitPrice !== 0
            && $whole > intdiv(PHP_INT_MAX, $unitPrice)
        ) {
            throw new OverflowException(
                'Line total exceeds integer range.'
            );
        }
        $wholeTotal = $whole * $unitPrice;
        $fractionTotal =
            $fraction * intdiv($unitPrice, self::SCALE)
            + intdiv(
                $fraction * ($unitPrice % self::SCALE)
                    + intdiv(self::SCALE, 2),
                self::SCALE
            );

        return self::add($wholeTotal, $fractionTotal);
    }

    public static function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0) {
            throw new InvalidArgumentException(
                'Money values cannot be negative.'
            );
        }
        if ($left > PHP_INT_MAX - $right) {
            throw new OverflowException(
                'Money total exceeds integer range.'
            );
        }

        return $left + $right;
    }
}
