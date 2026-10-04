<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_cancellations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->unique()
                ->constrained('sales')
                ->restrictOnDelete();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reason');

            $table->timestamp('cancelled_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sale_cancellations'
        );
    }
};
