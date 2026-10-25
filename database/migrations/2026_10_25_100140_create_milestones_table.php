<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->date('occurred_on');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category');
            $table->unsignedInteger('age_in_days')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
