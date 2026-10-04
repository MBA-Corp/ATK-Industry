<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            $table->string('purchase_number')
                ->unique();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();

            $table->string('supplier_invoice_number')
                ->nullable();

            $table->date('purchase_date');

            $table->string('status', 50)
                ->default('DRAFT');

            $table->unsignedBigInteger('subtotal')
                ->default(0);

            $table->unsignedBigInteger('operational_cost')
                ->default(0);

            $table->unsignedBigInteger('grand_total')
                ->default(0);

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('received_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'supplier_id',
                'supplier_invoice_number',
            ]);

            $table->index([
                'supplier_id',
                'purchase_date',
            ]);

            $table->index([
                'status',
                'purchase_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
