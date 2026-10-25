<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bath_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->string('bath_type')->nullable();
            $table->boolean('soap_used')->default(false);
            $table->boolean('shampoo_used')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bath_logs');
    }
};
