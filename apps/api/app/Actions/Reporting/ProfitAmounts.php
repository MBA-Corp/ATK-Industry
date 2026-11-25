<?php

namespace App\Actions\Reporting;

final class ProfitAmounts
{
    public static function summarize(
        string $sales,
        string $salesReturns,
        string $salesDiscounts,
        string $purchases,
        ?string $freight,
        string $purchaseReturns,
        string $purchaseDiscounts,
        ?string $opening,
        ?string $closing,
        ?string $perpetualHpp,
        string $expenses,
        string $method,
    ): array {
        $netSales = bcsub(bcsub($sales, $salesReturns, 0), $salesDiscounts, 0);
        $netPurchases = $freight === null ? null : bcsub(
            bcsub(bcadd($purchases, $freight, 0), $purchaseReturns, 0),
            $purchaseDiscounts,
            0,
        );
        $periodicHpp = $opening !== null && $closing !== null && $netPurchases !== null
            ? bcsub(bcadd($opening, $netPurchases, 6), $closing, 6)
            : null;
        $hpp = $method === 'periodic' ? $periodicHpp : $perpetualHpp;
        $grossProfit = $hpp === null ? null : bcsub($netSales, $hpp, 6);

        return [
            'gross_sales' => $sales,
            'sales_returns' => $salesReturns,
            'sales_discounts' => $salesDiscounts,
            'net_sales' => $netSales,
            'gross_purchases' => $purchases,
            'allocated_freight' => $freight,
            'purchase_returns' => $purchaseReturns,
            'purchase_discounts' => $purchaseDiscounts,
            'net_purchases' => $netPurchases,
            'opening_inventory' => $opening,
            'closing_inventory' => $closing,
            'periodic_hpp' => $periodicHpp,
            'perpetual_hpp' => $perpetualHpp,
            'hpp_method' => $method,
            'hpp' => $hpp,
            'gross_profit' => $grossProfit,
            'operating_expenses' => $expenses,
            'simple_net_profit' => $grossProfit === null ? null : bcsub($grossProfit, $expenses, 6),
            'margin_percentage' => self::margin($netSales, $grossProfit),
        ];
    }

    public static function margin(string $netSales, ?string $grossProfit): ?string
    {
        if ($grossProfit === null || bccomp($netSales, '0', 0) === 0) {
            return null;
        }

        return bcdiv(bcmul($grossProfit, '100', 6), $netSales, 6);
    }
}
