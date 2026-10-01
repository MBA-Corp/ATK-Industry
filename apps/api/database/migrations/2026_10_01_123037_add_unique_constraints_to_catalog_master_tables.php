<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(
                'name',
                'categories_name_unique'
            );
        });

        Schema::table('units', function (Blueprint $table) {
            $table->unique(
                'code',
                'units_code_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(
                'categories_name_unique'
            );
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropUnique(
                'units_code_unique'
            );
        });
    }
};
