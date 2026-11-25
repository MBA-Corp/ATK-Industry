<?php

namespace Tests\Unit;

use App\Actions\Reporting\ProfitAmounts;
use PHPUnit\Framework\TestCase;

class ProfitAmountsTest extends TestCase
{
    public function test_periodic_example_and_perpetual_cost_are_independent(): void
    {
        $report = ProfitAmounts::summarize(
            '18000000', '1800000', '500000', '5000000', '400000', '250000', '100000',
            '7000000.000000', '2000000.000000', '9900000.123456', '1700000', 'periodic',
        );

        self::assertSame('5050000', $report['net_purchases']);
        self::assertSame('10050000.000000', $report['hpp']);
        self::assertSame('15700000', $report['net_sales']);
        self::assertSame('5650000.000000', $report['gross_profit']);
        self::assertSame('3950000.000000', $report['simple_net_profit']);
        self::assertSame('9900000.123456', $report['perpetual_hpp']);
    }

    public function test_incomplete_perpetual_cost_remains_unknown_when_periodic_result_exists(): void
    {
        $report = ProfitAmounts::summarize(
            '1000', '0', '0', '100', '0', '0', '0',
            '0.000000', '50.000000', null, '0', 'perpetual',
        );

        self::assertSame('50.000000', $report['periodic_hpp']);
        self::assertNull($report['hpp']);
        self::assertNull($report['gross_profit']);
        self::assertNull($report['simple_net_profit']);
        self::assertNull($report['margin_percentage']);
    }
}
