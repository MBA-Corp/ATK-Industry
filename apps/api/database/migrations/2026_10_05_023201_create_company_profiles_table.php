<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'company_profiles',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'profile_key',
                    50
                )
                    ->default('PRIMARY')
                    ->unique();

                $table->string('name');

                $table->text('address');

                $table->string(
                    'phone',
                    50
                );

                $table->string(
                    'logo_path'
                )->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'company_profiles'
        );
    }
};
