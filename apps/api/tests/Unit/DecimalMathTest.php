<?php

namespace Tests\Unit;

use App\Support\DecimalMath;
use DomainException;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class DecimalMathTest extends TestCase
{
    public function test_normalize_preserves_six_decimal_places(): void
    {
        $this->assertSame(
            '1.250000',
            DecimalMath::normalize('1.25')
        );
        $this->assertSame(
            '-0.000001',
            DecimalMath::normalize('-0.000001')
        );
    }

    public function test_tiny_addition_remains_exact_at_high_values(): void
    {
        $this->assertSame(
            '999999999999.999999',
            DecimalMath::add(
                '999999999999.999998',
                '0.000001'
            )
        );
    }

    public function test_signed_addition_is_exact(): void
    {
        $this->assertSame(
            '1.750000',
            DecimalMath::add(
                '3.000000',
                '-1.250000'
            )
        );
    }

    public function test_quantity_conversion_multiplication_is_exact(): void
    {
        $this->assertSame(
            '15.000000',
            DecimalMath::multiply(
                '1.250000',
                '12.000000'
            )
        );

        $this->assertSame(
            '-1.125000',
            DecimalMath::multiply(
                '-2.250000',
                '0.500000'
            )
        );
    }

    public function test_multiplication_uses_half_up_rounding(): void
    {
        $this->assertSame(
            '0.000001',
            DecimalMath::multiply(
                '0.000001',
                '0.500000'
            )
        );
    }

    public function test_too_small_conversion_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        DecimalMath::multiply(
            '0.000001',
            '0.499999'
        );
    }

    public function test_addition_overflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);

        DecimalMath::add(
            '999999999999.999999',
            '0.000001'
        );
    }

    public function test_multiplication_overflow_is_rejected(): void
    {
        $this->expectException(OverflowException::class);

        DecimalMath::multiply(
            '999999999999.999999',
            '2'
        );
    }

    public function test_invalid_decimal_precision_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DecimalMath::normalize('1.1234567');
    }
}
