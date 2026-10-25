<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('typical_dose', 8, 2)->nullable();
            $table->string('unit', 8)->nullable();
            $table->unsignedInteger('min_interval_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_supplement')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};
