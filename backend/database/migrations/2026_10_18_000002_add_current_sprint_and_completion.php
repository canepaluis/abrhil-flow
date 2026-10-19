<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sprint_cards', function (Blueprint $table) {
            $table->boolean('is_completed')->default(false);
        });
        Schema::create('sprint_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('current_sprint_id')->nullable()->constrained('sprints')->nullOnDelete();
        });
        DB::table('sprint_settings')->insert(['id' => 1, 'current_sprint_id' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sprint_settings');
        Schema::table('sprint_cards', fn (Blueprint $table) => $table->dropColumn('is_completed'));
    }
};
