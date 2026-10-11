<?php

namespace App\Support;

use DomainException;
use InvalidArgumentException;
use OverflowException;

final class DecimalMath
{
    private const SCALE = 1000000;

    private const MAX_SCALED = 999999999999999999;

    public static function normalize(int|string $value): string
    {
        return self::format(self::parse($value));
    }

    public static function add(
        int|string $left,
        int|string $right
    ): string {
        $sum = self::parse($left) + self::parse($right);

        if (
            $sum > self::MAX_SCALED
            || $sum < -self::MAX_SCALED
        ) {
            throw new OverflowException(
                'Quantity exceeds DECIMAL(18,6).'
            );
        }

        return self::format($sum);
    }

    public static function multiply(
        int|string $left,
        int|string $right
    ): string {
        $leftScaled = self::parse($left);
        $rightScaled = self::parse($right);

        if ($leftScaled === 0 || $rightScaled === 0) {
            return '0.000000';
        }

        $leftAbs = abs($leftScaled);
        $rightAbs = abs($rightScaled);

        $leftWhole = intdiv($leftAbs, self::SCALE);
        $leftFraction = $leftAbs % self::SCALE;

        $rightWhole = intdiv($rightAbs, self::SCALE);
        $rightFraction = $rightAbs % self::SCALE;

        if (
            $leftWhole > intdiv(
                self::MAX_SCALED,
                $rightAbs
            )
        ) {
            throw new OverflowException(
                'Quantity product exceeds DECIMAL(18,6).'
            );
        }

        $scaledProduct = $leftWhole * $rightAbs;

        $scaledProduct = self::checkedAdd(
            $scaledProduct,
            $leftFraction * $rightWhole
        );

        $scaledProduct = self::checkedAdd(
            $scaledProduct,
            intdiv(
                $leftFraction * $rightFraction + 500000,
                self::SCALE
            )
        );

        if ($scaledProduct === 0) {
            throw new DomainException(
                'Quantity product is below inventory precision.'
            );
        }

        if (($leftScaled < 0) !== ($rightScaled < 0)) {
            $scaledProduct = -$scaledProduct;
        }

        return self::format($scaledProduct);
    }

    private static function parse(int|string $value): int
    {
        $value = (string) $value;

        if (
            preg_match(
                '/\A([+-]?)(\d{1,12})(?:\.(\d{1,6}))?\z/',
                $value,
                $matches
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Invalid DECIMAL(18,6) quantity.'
            );
        }

        $whole = (int) $matches[2];

        $fraction = (int) str_pad(
            $matches[3] ?? '',
            6,
            '0',
            STR_PAD_RIGHT
        );

        $scaled = $whole * self::SCALE + $fraction;

        return $matches[1] === '-'
            ? -$scaled
            : $scaled;
    }

    private static function checkedAdd(
        int $left,
        int $right
    ): int {
        if ($left > self::MAX_SCALED - $right) {
            throw new OverflowException(
                'Quantity exceeds DECIMAL(18,6).'
            );
        }

        return $left + $right;
    }

    private static function format(int $scaled): string
    {
        $absolute = abs($scaled);

        return ($scaled < 0 ? '-' : '')
            .intdiv($absolute, self::SCALE)
            .'.'
            .str_pad(
                (string) ($absolute % self::SCALE),
                6,
                '0',
                STR_PAD_LEFT
            );
    }
}
