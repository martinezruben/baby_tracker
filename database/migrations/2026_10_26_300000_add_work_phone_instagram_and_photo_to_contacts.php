<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // `phone` pasa a ser el teléfono personal; el del trabajo va aparte.
            $table->string('work_phone', 30)->nullable()->after('phone');
            $table->string('instagram', 30)->nullable()->after('email');
            $table->string('photo_path')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['work_phone', 'instagram', 'photo_path']);
        });
    }
};
