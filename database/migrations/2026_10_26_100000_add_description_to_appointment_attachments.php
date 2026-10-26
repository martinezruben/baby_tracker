<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_attachments', function (Blueprint $table) {
            // Para encontrarlos después: tipo (receta, examen…) y una descripción libre.
            $table->string('category', 20)->nullable()->after('original_name');
            $table->string('description', 500)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_attachments', function (Blueprint $table) {
            $table->dropColumn(['category', 'description']);
        });
    }
};
