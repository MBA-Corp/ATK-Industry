<?php

namespace App\Actions\Sales;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\ProductUnit;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSaleAction
{
    public function __construct(
        private ApplyStockMovementAction $applyStockMovement
    ) {}

    public function execute(
        array $validated,
        int $cashierUserId
    ): Sale {
        return DB::transaction(
            function () use (
                $validated,
                $cashierUserId
            ) {
                $productUnitIds = collect(
                    $validated['items']
                )
                    ->pluck('product_unit_id')
                    ->unique()
                    ->sort()
                    ->values();

                $productUnits = ProductUnit::query()
                    ->with([
                        'product:id,sku,name,is_active',
                        'unit:id,code,name',
                    ])
                    ->whereIn(
                        'id',
                        $productUnitIds
                    )
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach (
                    $productUnitIds as $productUnitId
                ) {
                    $productUnit = $productUnits->get(
                        (int) $productUnitId
                    );

                    if (
                        $productUnit === null
                        || ! $productUnit->is_active
                        || ! $productUnit->product
                            ->is_active
                    ) {
                        throw ValidationException::withMessages([
                            'items' => 'One or more products or product units are inactive.',
                        ]);
                    }
                }

                $preparedItems = [];
                $subtotal = 0;

                foreach (
                    $validated['items'] as $item
                ) {
                    $productUnit = $productUnits->get(
                        (int) $item[
                            'product_unit_id'
                        ]
                    );

                    if ($productUnit === null) {
                        throw ValidationException::withMessages([
                            'items' => 'Invalid product unit.',
                        ]);
                    }

                    $catalogPrice = (int) $productUnit
                        ->selling_price;

                    $salePrice = (int) (
                        $item['sale_unit_price']
                        ?? $catalogPrice
                    );

                    if ($salePrice <= 0) {
                        throw ValidationException::withMessages([
                            'items' => 'Sale price must be greater than zero.',
                        ]);
                    }

                    $quantity = (float) $item[
                        'quantity'
                    ];

                    $lineTotal = (int) round(
                        $quantity * $salePrice
                    );

                    $subtotal += $lineTotal;

                    $preparedItems[] = [
                        'product_unit' => $productUnit,

                        'quantity' => number_format(
                            $quantity,
                            6,
                            '.',
                            ''
                        ),

                        'conversion_to_base' => number_format(
                            (float) $productUnit
                                ->conversion_to_base,
                            6,
                            '.',
                            ''
                        ),

                        'catalog_unit_price' => $catalogPrice,

                        'sale_unit_price' => $salePrice,

                        'line_total' => $lineTotal,
                    ];
                }

                $soldAt = $validated['sold_at']
                    ?? now();
                $paymentTotal = collect(
                    $validated['payments']
                )->sum(
                    fn (array $payment) => (int) $payment['amount']
                );

                if ($paymentTotal !== $subtotal) {
                    throw ValidationException::withMessages([
                        'payments' => 'Total payment must exactly match the sale grand total.',
                    ]);
                }
                $sale = Sale::create([
                    'sale_number' => $this
                        ->generateSaleNumber(),

                    'client_reference' => $validated[
                            'client_reference'
                        ] ?? null,

                    'terminal_id' => $validated[
                        'terminal_id'
                    ],

                    'cashier_user_id' => $cashierUserId,

                    'status' => 'COMPLETED',

                    'subtotal' => $subtotal,

                    'grand_total' => $subtotal,

                    'sold_at' => $soldAt,

                    'completed_at' => $soldAt,
                ]);
                foreach (
                    $validated['payments'] as $payment
                ) {
                    $sale->payments()->create([
                        'payment_method' => $payment[
                            'payment_method'
                        ],

                        'amount' => $payment[
                            'amount'
                        ],

                        'reference' => $payment[
                                'reference'
                            ] ?? null,

                        'paid_at' => $soldAt,
                    ]);
                }

                /*
                 * Urut berdasarkan product ID untuk membantu
                 * mengurangi kemungkinan deadlock ketika
                 * checkout concurrent menyentuh produk sama.
                 */
                usort(
                    $preparedItems,
                    fn (array $a, array $b) => $a['product_unit']->product_id
                        <=>
                        $b['product_unit']->product_id
                );

                foreach ($preparedItems as $item) {
                    $productUnit = $item[
                        'product_unit'
                    ];

                    $saleItem = $sale
                        ->items()
                        ->create([
                            'product_id' => $productUnit
                                ->product_id,

                            'product_unit_id' => $productUnit
                                ->id,

                            'product_sku' => $productUnit
                                ->product
                                ->sku,

                            'product_name' => $productUnit
                                ->product
                                ->name,

                            'unit_code' => $productUnit
                                ->unit
                                ->code,

                            'quantity' => $item[
                                'quantity'
                            ],

                            'conversion_to_base' => $item[
                                'conversion_to_base'
                            ],

                            'catalog_unit_price' => $item[
                                'catalog_unit_price'
                            ],

                            'sale_unit_price' => $item[
                                'sale_unit_price'
                            ],

                            'line_total' => $item[
                                'line_total'
                            ],
                        ]);

                    $quantityDeltaBase = number_format(
                        -(
                            (float) $item['quantity']
                            * (float) $item[
                                'conversion_to_base'
                            ]
                        ),
                        6,
                        '.',
                        ''
                    );

                    $this->applyStockMovement->execute(
                        productId: $productUnit
                            ->product_id,

                        movementType: 'SALE',

                        sourceType: 'SALE_ITEM',

                        sourceId: $saleItem->id,

                        quantityDeltaBase: $quantityDeltaBase,

                        productUnitId: $productUnit->id,

                        sourceQuantity: $item[
                            'quantity'
                        ],

                        conversionToBase: $item[
                            'conversion_to_base'
                        ],

                        actorUserId: $cashierUserId,

                        terminalId: $validated[
                            'terminal_id'
                        ],

                        reason: 'POS sale',

                        occurredAt: $soldAt
                    );
                }

                return $sale->load([
                    'terminal:id,terminal_uuid,name',
                    'cashier:id,name,username',
                    'items',
                    'payments',
                ]);
            },
            3
        );
    }

    private function generateSaleNumber(): string
    {
        return 'SALE-'
            .now()->format('Ymd')
            .'-'
            .Str::upper(
                (string) Str::ulid()
            );
    }
}
