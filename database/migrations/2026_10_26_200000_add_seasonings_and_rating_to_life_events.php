<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_events', function (Blueprint $table) {
            // Solo para "Comida nueva": con qué se condimentó y si le gustó (FoodEnjoyment).
            $table->string('seasonings', 200)->nullable()->after('detail');
            $table->string('rating', 20)->nullable()->after('seasonings');
        });
    }

    public function down(): void
    {
        Schema::table('life_events', function (Blueprint $table) {
            $table->dropColumn(['seasonings', 'rating']);
        });
    }
};
