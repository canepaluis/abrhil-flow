<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->date('review_date')->nullable();
            $table->date('dev_review_date')->nullable();
            $table->string('dev_review_jira_url', 2048)->nullable();
            $table->date('production_release_date')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('sprints', fn (Blueprint $table) => $table->dropColumn(['review_date', 'dev_review_date', 'dev_review_jira_url', 'production_release_date']));
    }
};
