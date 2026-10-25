<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_settings', function (Blueprint $table) {
            $table->unsignedInteger('photo_quota_mb')->default(2048)->after('growth_chart_standard');
        });

        // Momentos del bebé: dientes, comida nueva, primeros pasos, viajes…
        Schema::create('life_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->string('type');
            $table->date('occurred_on');
            $table->string('title', 120)->nullable();
            $table->string('detail', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['baby_id', 'occurred_on']);
        });

        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->foreignId('life_event_id')->nullable()->constrained('life_events')->nullOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->string('original_name');
            $table->string('mime', 50);
            $table->unsignedBigInteger('size'); // bytes en disco: foto + miniatura (cuenta para la cuota)
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->dateTime('taken_at');
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('place_name', 120)->nullable();
            $table->text('caption')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
            $table->index(['baby_id', 'taken_at']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40); // siempre en minúsculas y sin espacios sobrantes
            $table->timestamps();
            $table->unique(['family_id', 'name']);
        });

        Schema::create('photo_tag', function (Blueprint $table) {
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['photo_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('life_events');
        Schema::table('family_settings', fn (Blueprint $t) => $t->dropColumn('photo_quota_mb'));
    }
};
