<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_medicine', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'medicine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_medicine');
    }
};
