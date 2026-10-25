<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('language', 5)->default('es');
            $table->string('timezone')->default('America/Santo_Domingo');
            $table->string('time_format', 2)->default('12');
            $table->string('volume_unit', 8)->default('ml');
            $table->string('weight_unit', 8)->default('kg');
            $table->string('height_unit', 8)->default('cm');
            $table->string('temperature_unit', 2)->default('C');
            $table->string('growth_chart_standard', 3)->default('who');
            $table->json('activity_visibility')->nullable();
            $table->json('sleep_locations')->nullable();
            $table->json('bath_types')->nullable();
            $table->json('nursery_mode')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_settings');
    }
};
