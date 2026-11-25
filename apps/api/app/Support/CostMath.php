<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class CostMath
{
    public static function round(string $value, int $scale = 6): string
    {
        if (preg_match('/\A-?\d+(?:\.\d+)?\z/', $value) !== 1) {
            throw ValidationException::withMessages(['cost' => 'Invalid decimal cost.']);
        }
        $adjustment = '0.'.str_repeat('0', $scale).'5';
        $result = bcadd($value, str_starts_with($value, '-') ? '-'.$adjustment : $adjustment, $scale);
        if (bccomp(ltrim($result, '-'), '999999999999999999999999.999999', 6) > 0) {
            throw ValidationException::withMessages(['cost' => 'Cost exceeds supported precision.']);
        }

        return $result;
    }

    public static function proportional(string $total, string $part, string $whole, int $scale = 6): string
    {
        if (bccomp($whole, '0', 6) <= 0) {
            throw ValidationException::withMessages(['cost' => 'Divisor must be positive.']);
        }

        return self::round(bcdiv(bcmul($total, $part, 30), $whole, 30), $scale);
    }
}
