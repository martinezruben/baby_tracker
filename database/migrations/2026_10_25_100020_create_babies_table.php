<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('babies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('birth_date');
            $table->string('gender')->nullable();
            $table->unsignedSmallInteger('feed_warning_minutes')->default(180);
            $table->unsignedSmallInteger('diaper_warning_minutes')->default(180);
            $table->string('feed_timer_from')->default('start');
            $table->json('feed_timer_types')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('babies');
    }
};
