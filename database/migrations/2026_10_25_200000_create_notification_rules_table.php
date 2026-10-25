<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baby_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('event_type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('offsets_minutes');
            $table->unsignedInteger('repeat_every_minutes')->nullable();
            $table->unsignedSmallInteger('max_repeats')->nullable();
            $table->boolean('requires_ack')->default(false);
            $table->string('ack_scope')->default('any');
            $table->json('snooze_options');
            $table->time('quiet_start')->nullable();
            $table->time('quiet_end')->nullable();
            $table->boolean('respect_quiet_hours')->default(true);
            $table->json('channels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notification_rule_caretaker', function (Blueprint $table) {
            $table->foreignId('notification_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->constrained()->cascadeOnDelete();
            $table->primary(['notification_rule_id', 'caretaker_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rule_caretaker');
        Schema::dropIfExists('notification_rules');
    }
};
