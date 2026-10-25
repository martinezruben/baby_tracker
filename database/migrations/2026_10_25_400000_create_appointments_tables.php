<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->string('reason', 160);
            $table->string('doctor_name', 120)->nullable();
            $table->string('specialty', 80)->nullable();
            $table->string('location', 160)->nullable();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->text('symptoms')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('follow_up_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('reminder_rule_id')->nullable()->constrained('notification_rules')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'scheduled_at']);
        });

        Schema::create('appointment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        // Medicina que recetó la cita (o suelta): cada toma programada es una fila en prescription_doses.
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->decimal('dose', 8, 2);
            $table->string('unit', 8)->nullable();
            $table->unsignedSmallInteger('interval_hours');
            $table->unsignedSmallInteger('total_doses');
            $table->dateTime('starts_at');
            $table->text('instructions')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('notification_rule_id')->nullable()->constrained('notification_rules')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prescription_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('status')->default('pending');
            $table->dateTime('given_at')->nullable();
            $table->foreignId('given_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->foreignId('medicine_log_id')->nullable()->constrained('medicine_logs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['prescription_id', 'scheduled_at']);
            $table->index(['status', 'scheduled_at']);
        });

        // Peso, talla… medidos en la cita quedan también en la curva de crecimiento.
        Schema::table('measurements', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('caretaker_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appointment_id');
        });
        Schema::dropIfExists('prescription_doses');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('appointment_attachments');
        Schema::dropIfExists('appointments');
    }
};
