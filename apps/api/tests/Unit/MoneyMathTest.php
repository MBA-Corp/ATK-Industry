<?php

namespace Tests\Unit;

use App\Support\MoneyMath;
use OverflowException;
use PHPUnit\Framework\TestCase;

class MoneyMathTest extends TestCase
{
    public function test_integer_quantity_calculates_exact_total(): void
    {
        $this->assertSame(
            15000,
            MoneyMath::lineTotal('3', 5000)
        );
    }

    public function test_fractional_quantity_uses_half_up_rounding(): void
    {
        $this->assertSame(
            1,
            MoneyMath::lineTotal('0.500000', 1)
        );
        $this->assertSame(
            0,
            MoneyMath::lineTotal('0.499999', 1)
        );
        $this->assertSame(
            15241,
            MoneyMath::lineTotal('0.123456', 123456)
        );
    }

    public function test_large_quantity_is_calculated_without_float(): void
    {
        $this->assertSame(
            1000000000000,
            MoneyMath::lineTotal('999999999999.999999', 1)
        );
    }

    public function test_line_total_overflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);
        MoneyMath::lineTotal(
            '999999999999.999999',
            1000000000
        );
    }

    public function test_addition_overflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);
        MoneyMath::add(PHP_INT_MAX, 1);
    }
}
