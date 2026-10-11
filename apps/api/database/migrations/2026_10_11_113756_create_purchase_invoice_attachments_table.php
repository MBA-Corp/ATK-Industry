<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_id')
                ->unique('purchase_invoice_purchase_unique')
                ->constrained('purchases')
                ->restrictOnDelete();

            $table->string('disk', 32)
                ->default('local');

            $table->string('path', 500);

            $table->string('original_name');

            $table->string('mime_type', 100);

            $table->unsignedBigInteger('size_bytes');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_attachments');
    }
};
