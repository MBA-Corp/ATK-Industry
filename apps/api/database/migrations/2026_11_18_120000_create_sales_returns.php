<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('replacement_sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->string('reason');
            $table->unsignedBigInteger('refund_due');
            $table->timestamp('returned_at')->index();
        });
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->decimal('quantity_base', 18, 6);
            $table->string('condition', 20);
            $table->unsignedBigInteger('refund_amount');
            $table->decimal('allocated_cost', 30, 6)->nullable();
            $table->decimal('cost_reversal', 30, 6)->nullable();
            $table->unique(['sale_return_id', 'sale_item_id']);
        });
        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->unsignedBigInteger('amount');
            $table->string('method', 30);
            $table->string('reference');
            $table->string('status')->default('MANUALLY_RECORDED');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
