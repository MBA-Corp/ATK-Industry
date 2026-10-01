<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->unique()
                ->constrained('products')
                ->restrictOnDelete();

            $table->decimal(
                'quantity_base',
                18,
                6
            )->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_balances'
        );
    }
};
