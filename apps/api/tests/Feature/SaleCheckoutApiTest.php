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

class SaleCheckoutApiTest extends TestCase
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

    private function terminal(
        bool $active = true
    ): Terminal {
        return Terminal::create([
            'terminal_uuid' => (string) Str::uuid(),
            'name' => 'POS Test',
            'is_active' => $active,
        ]);
    }

    private function product(
        string $sku = 'SALE-001',
        string $unitCode = 'PCS',
        float $conversion = 1,
        int $price = 5000,
        bool $productActive = true,
        bool $unitActive = true
    ): array {
        $unit = Unit::create([
            'code' => $unitCode,
            'name' => $unitCode,
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => $sku,
            'name' => "Product {$sku}",
            'is_active' => $productActive,
        ]);

        $productUnit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_to_base' => $conversion,
            'selling_price' => $price,
            'is_base' => $conversion === 1.0,
            'is_active' => $unitActive,
        ]);

        return [
            $product,
            $productUnit,
            $unit,
        ];
    }

    public function test_sale_checkout_reduces_stock_and_creates_stock_movement(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product();

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 10,
        ]);

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
                        'amount' => 10000,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'COMPLETED'
            )
            ->assertJsonPath(
                'data.subtotal',
                10000
            )
            ->assertJsonPath(
                'data.grand_total',
                10000
            )
            ->assertJsonPath(
                'data.cashier.id',
                $user->id
            )
            ->assertJsonPath(
                'data.terminal.id',
                $terminal->id
            )
            ->assertJsonPath(
                'data.items.0.product_sku',
                $product->sku
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                '2.000000'
            );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 8,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,
                'movement_type' => 'SALE',
                'source_type' => 'SALE_ITEM',
                'quantity_delta_base' => -2,
                'quantity_before_base' => 10,
                'quantity_after_base' => 8,
                'actor_user_id' => $user->id,
                'terminal_id' => $terminal->id,
            ]
        );
    }

    public function test_non_base_unit_reduces_stock_using_conversion_snapshot(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'SALE-PACK',
            'PACK',
            12,
            24000
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 100,
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
                        'amount' => 48000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.conversion_to_base',
                '12.000000'
            );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 76,
            ]
        );

        $this->assertDatabaseHas(
            'stock_movements',
            [
                'product_id' => $product->id,
                'quantity_delta_base' => -24,
                'quantity_after_base' => 76,
            ]
        );
    }

    public function test_sale_price_override_keeps_catalog_price_snapshot(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'SALE-PRICE',
            'PCS',
            1,
            15000
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 5,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 1,
                        'sale_unit_price' => 14000,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 14000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.subtotal',
                14000
            )
            ->assertJsonPath(
                'data.items.0.catalog_unit_price',
                15000
            )
            ->assertJsonPath(
                'data.items.0.sale_unit_price',
                14000
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                14000
            );

        $this->assertDatabaseHas(
            'sale_items',
            [
                'product_id' => $product->id,
                'catalog_unit_price' => 15000,
                'sale_unit_price' => 14000,
                'line_total' => 14000,
            ]
        );
    }

    public function test_insufficient_stock_rolls_back_entire_sale(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'SALE-LOW'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
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
                        'amount' => 10000,
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'sales',
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

    public function test_multiple_items_are_rolled_back_atomically_when_one_item_has_insufficient_stock(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $productA,
            $unitA,
        ] = $this->product(
            'SALE-ATOMIC-A',
            'PCS-A'
        );

        [
            $productB,
            $unitB,
        ] = $this->product(
            'SALE-ATOMIC-B',
            'PCS-B'
        );

        InventoryBalance::create([
            'product_id' => $productA->id,
            'quantity_base' => 10,
        ]);

        InventoryBalance::create([
            'product_id' => $productB->id,
            'quantity_base' => 1,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $unitA->id,
                        'quantity' => 2,
                    ],
                    [
                        'product_unit_id' => $unitB->id,
                        'quantity' => 5,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 35000,
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'sales',
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
                'product_id' => $productA->id,
                'quantity_base' => 10,
            ]
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $productB->id,
                'quantity_base' => 1,
            ]
        );
    }

    public function test_inactive_terminal_is_rejected(): void
    {
        $user = $this->userWithPermission();

        $terminal = $this->terminal(
            false
        );

        [
            ,
            $productUnit,
        ] = $this->product(
            'SALE-TERMINAL'
        );

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
                        'payment_method' => 'CASH',
                        'amount' => 5000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'terminal_id'
            );

        $this->assertDatabaseCount(
            'sales',
            0
        );
    }

    public function test_inactive_product_is_rejected(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product(
            'SALE-INACTIVE-PRODUCT',
            'PCS',
            1,
            5000,
            false,
            true
        );

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
                        'payment_method' => 'CASH',
                        'amount' => 5000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.product_unit_id'
            );
    }

    public function test_inactive_product_unit_is_rejected(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product(
            'SALE-INACTIVE-UNIT',
            'PCS',
            1,
            5000,
            true,
            false
        );

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
                        'payment_method' => 'CASH',
                        'amount' => 5000,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'items.0.product_unit_id'
            );
    }

    public function test_cashier_identity_comes_from_authenticated_user(): void
    {
        $user = $this->userWithPermission();
        $otherUser = User::factory()->create();

        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'SALE-CASHIER'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 5,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                /*
                 * Harus diabaikan karena field ini
                 * tidak ada di validated payload.
                 */
                'cashier_user_id' => $otherUser->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 1,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 5000,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.cashier.id',
                $user->id
            );

        $this->assertDatabaseHas(
            'sales',
            [
                'cashier_user_id' => $user->id,
            ]
        );

        $this->assertDatabaseMissing(
            'sales',
            [
                'cashier_user_id' => $otherUser->id,
            ]
        );
    }

    public function test_user_without_permission_cannot_create_sale(): void
    {
        $user = User::factory()->create();

        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product(
            'SALE-FORBIDDEN'
        );

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
            ])
            ->assertForbidden();

        $this->assertDatabaseCount(
            'sales',
            0
        );
    }

    public function test_guest_cannot_create_sale(): void
    {
        $terminal = $this->terminal();

        [
            ,
            $productUnit,
        ] = $this->product(
            'SALE-GUEST'
        );

        $this
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,
                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount(
            'sales',
            0
        );
    }

    public function test_multiple_lines_for_same_product_cannot_oversell_combined_stock(): void
    {
        $user = $this->userWithPermission();
        $terminal = $this->terminal();

        [
            $product,
            $productUnit,
        ] = $this->product(
            'SALE-SAME-PRODUCT'
        );

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 10,
        ]);

        $this
            ->actingAs($user)
            ->postJson('/api/v1/sales', [
                'terminal_id' => $terminal->id,

                'items' => [
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 6,
                    ],
                    [
                        'product_unit_id' => $productUnit->id,
                        'quantity' => 6,
                    ],
                ],

                'payments' => [
                    [
                        'payment_method' => 'CASH',
                        'amount' => 60000,
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'sales',
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
                'quantity_base' => 10,
            ]
        );
    }
}
