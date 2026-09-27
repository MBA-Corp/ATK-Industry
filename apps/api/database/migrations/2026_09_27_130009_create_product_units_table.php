<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products');

            $table->foreignId('unit_id')
                ->constrained('units');

            $table->decimal('conversion_to_base', 18, 6);
            $table->unsignedBigInteger('selling_price');
            $table->boolean('is_base')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unique(['product_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
