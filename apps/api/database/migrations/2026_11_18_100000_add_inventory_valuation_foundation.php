<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_costs', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->decimal('inventory_value', 30, 6)->nullable();
            $table->timestamps();
        });
        Schema::create('inventory_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('value_before', 30, 6)->nullable();
            $table->decimal('value_delta', 30, 6)->nullable();
            $table->decimal('value_after', 30, 6)->nullable();
            $table->string('policy')->default('moving_average_excluding_freight_provisional');
            $table->timestamp('created_at');
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_total', 30, 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('cost_total'));
        Schema::dropIfExists('inventory_valuations');
        Schema::dropIfExists('inventory_costs');
    }
};
