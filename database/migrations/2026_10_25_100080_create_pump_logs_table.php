<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pump_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->decimal('left_amount', 8, 2)->nullable();
            $table->decimal('right_amount', 8, 2)->nullable();
            $table->decimal('total_amount', 8, 2)->nullable();
            $table->string('unit', 8)->default('ml');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pump_logs');
    }
};
