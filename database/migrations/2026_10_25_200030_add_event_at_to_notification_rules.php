<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recordatorios sueltos: el evento (fecha y hora) al que se refiere la regla.
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->dateTime('event_at')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('notification_rules', function (Blueprint $table) {
            $table->dropColumn('event_at');
        });
    }
};
