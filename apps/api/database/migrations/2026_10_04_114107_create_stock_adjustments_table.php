<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();

            $table->string('adjustment_number')
                ->unique();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_unit_id')
                ->constrained('product_units')
                ->restrictOnDelete();

            $table->string(
                'adjustment_type',
                50
            );

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

            $table->decimal(
                'quantity_delta_base',
                18,
                6
            );

            $table->decimal(
                'quantity_before_base',
                18,
                6
            );

            $table->decimal(
                'quantity_after_base',
                18,
                6
            );

            $table->text('reason');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp(
                'occurred_at'
            );

            $table->timestamp(
                'created_at'
            )->useCurrent();

            $table->index(
                [
                    'product_id',
                    'occurred_at',
                ],
                'stock_adjustment_product_time'
            );

            $table->index(
                [
                    'adjustment_type',
                    'occurred_at',
                ],
                'stock_adjustment_type_time'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_adjustments'
        );
    }
};
