<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'purchase_receipt_items',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId(
                    'purchase_receipt_id'
                )
                    ->constrained(
                        'purchase_receipts'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'purchase_item_id'
                )
                    ->constrained(
                        'purchase_items'
                    )
                    ->restrictOnDelete();

                $table->decimal(
                    'qty_received',
                    18,
                    6
                );

                $table->timestamps();

                $table->unique(
                    [
                        'purchase_receipt_id',
                        'purchase_item_id',
                    ],
                    'purchase_receipt_item_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_receipt_items'
        );
    }
};
