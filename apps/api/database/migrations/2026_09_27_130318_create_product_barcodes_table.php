<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products');

            $table->foreignId('product_unit_id')
                ->nullable()
                ->constrained('product_units');

            $table->string('barcode')->unique();
            $table->boolean('is_primary')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
