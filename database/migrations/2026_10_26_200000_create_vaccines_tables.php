<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_settings', function (Blueprint $table) {
            $table->string('vaccine_schedule_name', 80)->default('República Dominicana')->after('photo_quota_mb');
        });

        // Esquema de vacunación de la familia: se carga desde la cartilla, nunca va fijo en el código.
        Schema::create('vaccine_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('vaccine_name', 80);
            $table->string('dose_label', 40)->nullable();
            $table->unsignedSmallInteger('age_days'); // edad recomendada, en días desde el nacimiento (0 = al nacer)
            $table->string('notes', 200)->nullable();
            $table->timestamps();
            $table->index(['family_id', 'age_days']);
        });

        // Lo que se le aplicó a cada bebé; puede estar o no en el esquema (vacunas privadas, extra…).
        Schema::create('vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vaccine_dose_id')->nullable()->constrained('vaccine_doses')->nullOnDelete();
            $table->string('vaccine_name', 80);
            $table->string('dose_label', 40)->nullable();
            $table->dateTime('administered_at');
            $table->string('lot', 60)->nullable();
            $table->string('place', 120)->nullable();
            $table->string('administered_by', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'administered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vaccinations');
        Schema::dropIfExists('vaccine_doses');
        Schema::table('family_settings', fn (Blueprint $t) => $t->dropColumn('vaccine_schedule_name'));
    }
};
