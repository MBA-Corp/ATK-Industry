<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('request_hash', 64);
            $table->date('expense_date')->index();
            $table->string('category', 80);
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('version');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
        Schema::create('operating_expense_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id')->constrained('operating_expenses')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->date('expense_date');
            $table->string('category', 80);
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->unique(['expense_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_expense_revisions');
        Schema::dropIfExists('operating_expenses');
    }
};
