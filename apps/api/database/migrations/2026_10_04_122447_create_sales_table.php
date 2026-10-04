<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();

            $table->string('sale_number')
                ->unique();

            $table->uuid('client_reference')
                ->nullable()
                ->unique();

            $table->foreignId('terminal_id')
                ->constrained('terminals')
                ->restrictOnDelete();

            $table->foreignId('cashier_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('status', 50)
                ->default('DRAFT');

            $table->unsignedBigInteger('subtotal')
                ->default(0);

            $table->unsignedBigInteger('grand_total')
                ->default(0);

            $table->timestamp('sold_at');

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'sold_at',
            ]);

            $table->index([
                'cashier_user_id',
                'sold_at',
            ]);

            $table->index([
                'terminal_id',
                'sold_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
