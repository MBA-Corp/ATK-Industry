<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->string('supplier_sku')
                ->nullable();

            $table->unsignedBigInteger(
                'last_purchase_price'
            )->nullable();

            $table->boolean('is_preferred')
                ->default(false);

            $table->boolean('is_active')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'product_id',
                'supplier_id',
            ]);

            $table->index([
                'product_id',
                'is_active',
            ]);

            $table->index([
                'supplier_id',
                'is_active',
            ]);

            $table->index([
                'product_id',
                'is_preferred',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_suppliers'
        );
    }
};
