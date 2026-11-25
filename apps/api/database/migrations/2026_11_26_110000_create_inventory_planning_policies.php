<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_planning_policies', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->date('history_from');
            $table->unsignedSmallInteger('lead_time_days');
            $table->unsignedSmallInteger('demand_window_days');
            $table->unsignedSmallInteger('minimum_history_days');
            $table->decimal('quantity_step', 18, 6);
            $table->decimal('minimum_stock', 18, 6)->nullable();
            $table->decimal('maximum_stock', 18, 6)->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_planning_policies');
    }
};
