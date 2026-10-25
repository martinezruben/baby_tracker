<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_rule_id')->constrained()->cascadeOnDelete();
            $table->dateTime('event_at');
            $table->integer('offset_minutes')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['notification_rule_id', 'event_at', 'offset_minutes'], 'occurrence_unique');
            $table->index(['status', 'event_at']);
        });

        Schema::create('notification_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caretaker_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->dateTime('next_send_at')->nullable();
            $table->unsignedSmallInteger('sent_count')->default(0);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->dateTime('last_sent_at')->nullable();
            $table->dateTime('snoozed_until')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('caretakers')->nullOnDelete();
            $table->timestamps();
            $table->unique(['notification_occurrence_id', 'caretaker_id'], 'target_unique');
            $table->index(['status', 'next_send_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_target_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('status');
            $table->text('error')->nullable();
            $table->dateTime('sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_targets');
        Schema::dropIfExists('notification_occurrences');
    }
};
