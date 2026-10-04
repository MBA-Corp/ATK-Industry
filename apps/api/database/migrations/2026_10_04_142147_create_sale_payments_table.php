<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_id')
                ->constrained('sales')
                ->restrictOnDelete();

            $table->string(
                'payment_method',
                20
            );

            $table->unsignedBigInteger(
                'amount'
            );

            $table->string('reference')
                ->nullable();

            $table->timestamp('paid_at');

            $table->timestamps();

            $table->unique(
                [
                    'sale_id',
                    'payment_method',
                ],
                'sale_payments_sale_method_unique'
            );

            $table->index([
                'payment_method',
                'paid_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sale_payments'
        );
    }
};
