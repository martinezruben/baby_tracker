<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('active_breast_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->string('active_side')->nullable();
            $table->boolean('is_paused')->default(false);
            $table->unsignedInteger('left_seconds')->default(0);
            $table->unsignedInteger('right_seconds')->default(0);
            $table->unsignedInteger('pause_seconds')->default(0);
            $table->dateTime('started_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_breast_feeds');
    }
};
