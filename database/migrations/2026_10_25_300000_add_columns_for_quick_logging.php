<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diaper_logs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('cream_applied');
        });

        // Lactancia: se guarda cuánto tiempo estuvo en cada lado.
        Schema::table('feed_logs', function (Blueprint $table) {
            $table->unsignedInteger('left_seconds')->nullable()->after('pause_seconds');
            $table->unsignedInteger('right_seconds')->nullable()->after('left_seconds');
        });

        // Instante de la última acción del cronómetro: desde ahí corre el lado activo.
        Schema::table('active_breast_feeds', function (Blueprint $table) {
            $table->dateTime('ticked_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('active_breast_feeds', fn (Blueprint $t) => $t->dropColumn('ticked_at'));
        Schema::table('feed_logs', fn (Blueprint $t) => $t->dropColumn(['left_seconds', 'right_seconds']));
        Schema::table('diaper_logs', fn (Blueprint $t) => $t->dropColumn('notes'));
    }
};
