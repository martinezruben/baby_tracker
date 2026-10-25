<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->nullable()->constrained()->nullOnDelete();
            $table->date('measured_on');
            $table->string('type');
            $table->decimal('value', 8, 2);
            $table->string('unit', 8);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('measurements');
    }
};
