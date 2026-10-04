<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalePaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Owner',
            'code' => 'owner',
        ]);

        $permission = Permission::create([
            'name' => 'Create Sale',
            'code' => 'sale.create',
        ]);

        $role->permissions()->attach(
            $permission->id
        );

        $user->roles()->attach(
            $role->id
        );

        return $user;
    }

    private function terminal(): Terminal
    {
        return Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Payment Test',
            'is_active' => true,
        ]);
    }

    private function product(): array
    {
        $unit = Unit::create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'PAY-001',
            'name' => 'Payment Test Product',
            'is_active' => true,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => 1,
            'selling_price' => 10000,
            'is_base' => true,
            'is_active' => true,
        ]);

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 100,
        ]);

        return [
            $product,
            $productUnit,
        ];
    }

    public function test_cash_payment_can_complete_sale(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 2,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 20000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.grand_total',
                20000
            )
            ->assertJsonPath(
                'data.payments.0.payment_method',
                'CASH'
            )
            ->assertJsonPath(
                'data.payments.0.amount',
                20000
            );

        $this->assertDatabaseHas(
            'sale_payments',
            [
                'payment_method' => 'CASH',
                'amount' => 20000,
            ]
        );
    }

    public function test_qris_payment_can_complete_sale(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 1,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'QRIS',
                        'amount' => 10000,
                        'reference' => 'QRIS-TEST-001',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.payments.0.payment_method',
                'QRIS'
            )
            ->assertJsonPath(
                'data.payments.0.reference',
                'QRIS-TEST-001'
            );

        $this->assertDatabaseHas(
            'sale_payments',
            [
                'payment_method' => 'QRIS',
                'amount' => 10000,
                'reference' => 'QRIS-TEST-001',
            ]
        );
    }

    public function test_cash_and_qris_split_payment_can_complete_sale(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product();

        $response = $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 2,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 7000,
                    ],
                    [
                        'payment_method' => 'QRIS',
                        'amount' => 13000,
                        'reference' => 'QRIS-SPLIT-001',
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.grand_total',
                20000
            );

        $this->assertCount(
            2,
            $response->json('data.payments')
        );

        $this->assertDatabaseHas(
            'sale_payments',
            [
                'payment_method' => 'CASH',
                'amount' => 7000,
            ]
        );

        $this->assertDatabaseHas(
            'sale_payments',
            [
                'payment_method' => 'QRIS',
                'amount' => 13000,
            ]
        );
    }

    public function test_underpayment_is_rejected_and_sale_is_rolled_back(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 2,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 19000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'payments'
            );

        $this->assertDatabaseCount(
            'sales',
            0
        );

        $this->assertDatabaseCount(
            'sale_payments',
            0
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 100,
            ]
        );
    }

    public function test_overpayment_is_rejected_and_sale_is_rolled_back(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 2,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 21000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'payments'
            );

        $this->assertDatabaseCount(
            'sales',
            0
        );

        $this->assertDatabaseCount(
            'sale_payments',
            0
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 100,
            ]
        );
    }

    public function test_duplicate_payment_method_is_rejected(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 2,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 10000,
                    ],
                    [
                        'payment_method' => 'CASH',
                        'amount' => 10000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'payments.1.payment_method'
            );

        $this->assertDatabaseCount(
            'sales',
            0
        );
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product();

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 1,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'TRANSFER',
                        'amount' => 10000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'payments.0.payment_method'
            );

        $this->assertDatabaseCount(
            'sales',
            0
        );
    }

    public function test_payment_is_rolled_back_when_stock_is_insufficient(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product();

        InventoryBalance::query()
            ->where(
                'product_id',
                $product->id
            )
            ->update([
                'quantity_base' => 1,
            ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 2,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 20000,
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'sales',
            0
        );

        $this->assertDatabaseCount(
            'sale_payments',
            0
        );

        $this->assertDatabaseCount(
            'sale_items',
            0
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 1,
            ]
        );
    }
}
