<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_unit_id')
                ->constrained('product_units')
                ->restrictOnDelete();

            $table->unsignedBigInteger('old_price')
                ->nullable();

            $table->unsignedBigInteger('new_price');

            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('reason')
                ->nullable();

            $table->timestamp('changed_at')
                ->useCurrent();

            $table->index([
                'product_unit_id',
                'changed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_price_histories'
        );
    }
};
