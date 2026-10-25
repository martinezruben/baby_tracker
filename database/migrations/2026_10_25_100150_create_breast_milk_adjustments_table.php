<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breast_milk_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->decimal('amount', 8, 2);
            $table->string('unit', 8)->default('ml');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breast_milk_adjustments');
    }
};
