<?php

namespace Tests\Feature;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplyStockMovementActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_creates_inventory_balance(): void
    {
        $product = Product::create([
            'sku' => 'STOCK-001',
            'name' => 'Stock Test',
        ]);

        $movement = app(
            ApplyStockMovementAction::class
        )->execute(
            productId: $product->id,
            movementType: 'ADJUSTMENT',
            sourceType: 'TEST',
            sourceId: 1,
            quantityDeltaBase: '10.000000'
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 10,
            ]
        );

        $this->assertSame(
            '0.000000',
            $movement->quantity_before_base
        );

        $this->assertSame(
            '10.000000',
            $movement->quantity_delta_base
        );

        $this->assertSame(
            '10.000000',
            $movement->quantity_after_base
        );
    }

    public function test_multiple_movements_update_existing_balance(): void
    {
        $product = Product::create([
            'sku' => 'STOCK-002',
            'name' => 'Stock Test',
        ]);

        $action = app(
            ApplyStockMovementAction::class
        );

        $action->execute(
            productId: $product->id,
            movementType: 'ADJUSTMENT',
            sourceType: 'TEST',
            sourceId: 1,
            quantityDeltaBase: '10.000000'
        );

        $movement = $action->execute(
            productId: $product->id,
            movementType: 'ADJUSTMENT',
            sourceType: 'TEST',
            sourceId: 2,
            quantityDeltaBase: '5.500000'
        );

        $balance = InventoryBalance::query()
            ->where(
                'product_id',
                $product->id
            )
            ->firstOrFail();

        $this->assertSame(
            '15.500000',
            $balance->quantity_base
        );

        $this->assertSame(
            '10.000000',
            $movement->quantity_before_base
        );

        $this->assertSame(
            '15.500000',
            $movement->quantity_after_base
        );
    }

    public function test_negative_movement_reduces_stock(): void
    {
        $product = Product::create([
            'sku' => 'STOCK-003',
            'name' => 'Stock Test',
        ]);

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 10,
        ]);

        $movement = app(
            ApplyStockMovementAction::class
        )->execute(
            productId: $product->id,
            movementType: 'ADJUSTMENT',
            sourceType: 'TEST',
            sourceId: 1,
            quantityDeltaBase: '-4.000000'
        );

        $this->assertSame(
            '6.000000',
            $movement->quantity_after_base
        );

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 6,
            ]
        );
    }

    public function test_movement_cannot_make_stock_negative(): void
    {
        $product = Product::create([
            'sku' => 'STOCK-004',
            'name' => 'Stock Test',
        ]);

        InventoryBalance::create([
            'product_id' => $product->id,
            'quantity_base' => 3,
        ]);

        try {
            app(
                ApplyStockMovementAction::class
            )->execute(
                productId: $product->id,
                movementType: 'SALE',
                sourceType: 'TEST',
                sourceId: 1,
                quantityDeltaBase: '-5.000000'
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'quantity_delta_base',
                $exception->errors()
            );
        }

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 3,
            ]
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );
    }

    public function test_same_source_cannot_be_applied_twice(): void
    {
        $product = Product::create([
            'sku' => 'STOCK-005',
            'name' => 'Stock Test',
        ]);

        $action = app(
            ApplyStockMovementAction::class
        );

        $action->execute(
            productId: $product->id,
            movementType: 'PURCHASE_RECEIPT',
            sourceType: 'PURCHASE_RECEIPT_ITEM',
            sourceId: 123,
            quantityDeltaBase: '10.000000'
        );

        try {
            $action->execute(
                productId: $product->id,
                movementType: 'PURCHASE_RECEIPT',
                sourceType: 'PURCHASE_RECEIPT_ITEM',
                sourceId: 123,
                quantityDeltaBase: '10.000000'
            );

            $this->fail(
                'Expected ValidationException.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'source',
                $exception->errors()
            );
        }

        $this->assertDatabaseHas(
            'inventory_balances',
            [
                'product_id' => $product->id,
                'quantity_base' => 10,
            ]
        );

        $this->assertDatabaseCount(
            'stock_movements',
            1
        );
    }

    public function test_movement_records_unit_quantity_conversion_and_actor(): void
    {
        $user = User::factory()->create();

        $product = Product::create([
            'sku' => 'STOCK-006',
            'name' => 'Stock Test',
        ]);

        $unit = Unit::create([
            'code' => 'PACK',
            'name' => 'Pack',
            'is_active' => true,
        ]);

        $productUnit = $product
            ->productUnits()
            ->create([
                'unit_id' => $unit->id,
                'conversion_to_base' => 12,
                'selling_price' => 10000,
                'is_base' => false,
                'is_active' => true,
            ]);

        $movement = app(
            ApplyStockMovementAction::class
        )->execute(
            productId: $product->id,
            movementType: 'PURCHASE_RECEIPT',
            sourceType: 'PURCHASE_RECEIPT_ITEM',
            sourceId: 999,
            quantityDeltaBase: '24.000000',
            productUnitId: $productUnit->id,
            sourceQuantity: '2.000000',
            conversionToBase: '12.000000',
            actorUserId: $user->id,
            reason: 'Purchase receiving'
        );

        $this->assertSame(
            $productUnit->id,
            $movement->product_unit_id
        );

        $this->assertSame(
            '2.000000',
            $movement->source_quantity
        );

        $this->assertSame(
            '12.000000',
            $movement->conversion_to_base
        );

        $this->assertSame(
            '24.000000',
            $movement->quantity_delta_base
        );

        $this->assertSame(
            $user->id,
            $movement->actor_user_id
        );

        $this->assertSame(
            'Purchase receiving',
            $movement->reason
        );
    }
}
