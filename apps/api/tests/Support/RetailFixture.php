<?php

namespace Tests\Support;

use App\Actions\Inventory\ApplyStockMovementAction;
use App\Actions\Sales\CreateSaleAction;
use App\Models\Product;
use App\Models\Role;
use App\Models\Terminal;
use App\Models\Unit;
use App\Models\User;
use App\Support\MoneyMath;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Str;

trait RetailFixture
{
    private function retailFixture(string $quantity = '3', int $price = 101): array
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', 'owner')->firstOrFail());
        $product = Product::create(['sku' => 'SYNTHETIC-RETAIL', 'name' => 'Synthetic product']);
        $unit = Unit::create(['code' => 'PCS', 'name' => 'Piece', 'is_active' => true]);
        $pu = $product->productUnits()->create(['unit_id' => $unit->id, 'conversion_to_base' => '1',
            'selling_price' => $price, 'is_base' => true, 'is_active' => true]);
        $terminal = Terminal::create(['terminal_uuid' => (string) Str::uuid(), 'name' => 'Test', 'is_active' => true]);
        app(ApplyStockMovementAction::class)->execute($product->id, 'OPENING', 'SYNTHETIC', 1, '10', incomingValue: '1000');
        $amount = MoneyMath::lineTotal($quantity, $price);
        $sale = app(CreateSaleAction::class)->execute(['terminal_id' => $terminal->id,
            'items' => [['product_unit_id' => $pu->id, 'quantity' => $quantity]],
            'payments' => [['payment_method' => 'CASH', 'amount' => $amount]]], $user->id);

        return [$user, $product, $pu, $sale];
    }
}
