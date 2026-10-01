<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')
                ->constrained('purchases')
                ->cascadeOnDelete();

            $table->foreignId('product_supplier_id')
                ->constrained('product_suppliers')
                ->restrictOnDelete();

            $table->foreignId('product_unit_id')
                ->constrained('product_units')
                ->restrictOnDelete();

            $table->decimal(
                'qty_ordered',
                18,
                6
            );

            $table->decimal(
                'qty_received',
                18,
                6
            )->default(0);

            $table->unsignedBigInteger(
                'purchase_price'
            );

            $table->unsignedBigInteger(
                'line_total'
            );

            $table->timestamps();

            $table->unique(
                [
                    'purchase_id',
                    'product_supplier_id',
                    'product_unit_id',
                ],
                'purchase_items_unique'
            );

            $table->index(
                [
                    'product_supplier_id',
                    'product_unit_id',
                ],
                'purchase_items_product_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
