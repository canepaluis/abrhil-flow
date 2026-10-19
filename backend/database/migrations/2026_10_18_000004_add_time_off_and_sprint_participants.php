<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'start_date', 'end_date']);
        });
        Schema::create('sprint_user', function (Blueprint $table) {
            $table->foreignId('sprint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['sprint_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_user');
        Schema::dropIfExists('user_time_offs');
    }
};
