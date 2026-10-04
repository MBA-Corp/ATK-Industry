<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_unit_id')
                ->constrained('product_units')
                ->restrictOnDelete();

            $table->string('product_sku');
            $table->string('product_name');
            $table->string('unit_code', 50);

            $table->decimal(
                'quantity',
                18,
                6
            );

            $table->decimal(
                'conversion_to_base',
                18,
                6
            );

            $table->unsignedBigInteger(
                'catalog_unit_price'
            );

            $table->unsignedBigInteger(
                'sale_unit_price'
            );

            $table->unsignedBigInteger(
                'line_total'
            );

            $table->timestamps();

            $table->index(
                [
                    'sale_id',
                    'product_id',
                ],
                'sale_items_sale_product_index'
            );

            $table->index(
                [
                    'product_id',
                    'product_unit_id',
                ],
                'sale_items_product_unit_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
