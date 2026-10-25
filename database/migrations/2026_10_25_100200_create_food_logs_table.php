<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->foreignId('feed_log_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->decimal('amount', 8, 2)->nullable();
            $table->string('unit', 8)->nullable();
            $table->string('enjoyment')->nullable();
            $table->boolean('had_reaction')->default(false);
            $table->text('reaction_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_logs');
    }
};
