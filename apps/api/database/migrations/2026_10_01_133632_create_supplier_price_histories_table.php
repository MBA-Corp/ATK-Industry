<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'supplier_price_histories',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'product_supplier_id'
                )
                    ->constrained(
                        'product_suppliers'
                    )
                    ->restrictOnDelete();

                $table->unsignedBigInteger(
                    'old_price'
                )->nullable();

                $table->unsignedBigInteger(
                    'new_price'
                );

                $table->foreignId(
                    'changed_by'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'changed_at'
                )->useCurrent();

                $table->index([
                    'product_supplier_id',
                    'changed_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_price_histories'
        );
    }
};
