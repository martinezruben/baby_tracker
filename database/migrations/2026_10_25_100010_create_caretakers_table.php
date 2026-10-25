<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caretakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('login_id', 8);
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('role')->default('user');
            $table->string('pin')->nullable();
            $table->string('badge_color', 7)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('language', 5)->nullable();
            $table->unique(['family_id', 'login_id']);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caretakers');
    }
};
