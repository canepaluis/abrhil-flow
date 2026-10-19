<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('members_count');
            $table->unsignedInteger('daily_minutes');
            $table->json('working_days');
            $table->timestamps();
        });

        Schema::create('sprint_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('estimated_minutes');
            $table->timestamps();
            $table->unique(['sprint_id', 'card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_cards');
        Schema::dropIfExists('sprints');
    }
};
