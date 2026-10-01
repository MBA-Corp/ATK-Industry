<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->foreignId('product_unit_id')
                ->nullable()
                ->constrained('product_units')
                ->restrictOnDelete();

            $table->string(
                'movement_type',
                50
            );

            $table->string(
                'source_type',
                100
            );

            $table->unsignedBigInteger(
                'source_id'
            );

            $table->decimal(
                'source_quantity',
                18,
                6
            )->nullable();

            $table->decimal(
                'conversion_to_base',
                18,
                6
            )->nullable();

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

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained(
                    'users'
                )
                ->nullOnDelete();

            $table->foreignId('terminal_id')
                ->nullable()
                ->constrained(
                    'terminals'
                )
                ->nullOnDelete();

            $table->string('reason')
                ->nullable();

            $table->timestamp(
                'occurred_at'
            );

            $table->timestamp(
                'created_at'
            )->useCurrent();

            $table->unique(
                [
                    'source_type',
                    'source_id',
                ],
                'stock_movement_source_unique'
            );

            $table->index(
                [
                    'product_id',
                    'occurred_at',
                ],
                'stock_movement_product_time'
            );

            $table->index(
                [
                    'movement_type',
                    'occurred_at',
                ],
                'stock_movement_type_time'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_movements'
        );
    }
};
