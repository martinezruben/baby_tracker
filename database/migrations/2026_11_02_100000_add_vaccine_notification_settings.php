<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_settings', function (Blueprint $table) {
            // Cada cuántos días se repite el aviso de una dosis pendiente ya vencida (0 = no se repite).
            $table->unsignedTinyInteger('vaccine_repeat_days')->default(7);
            // Hora local (HH:MM) a la que salen todos los avisos de vacunas.
            $table->string('vaccine_notify_time', 5)->default('09:00');
        });
    }

    public function down(): void
    {
        Schema::table('family_settings', function (Blueprint $table) {
            $table->dropColumn(['vaccine_repeat_days', 'vaccine_notify_time']);
        });
    }
};
