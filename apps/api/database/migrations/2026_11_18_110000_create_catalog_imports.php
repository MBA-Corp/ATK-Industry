<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->string('content_hash', 64)->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('row_count');
            $table->timestamp('created_at');
        });
        Schema::create('catalog_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_import_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->json('source_metadata');
            $table->unique(['catalog_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_import_rows');
        Schema::dropIfExists('catalog_imports');
    }
};
