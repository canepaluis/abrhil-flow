<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('workflow_state')->default('documentation_pending');
            $table->date('requested_at')->nullable();
            $table->foreignId('documenter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('developer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('correction_origin')->nullable();
            $table->text('correction_note')->nullable();
            $table->string('demo_key')->nullable()->unique();
        });
        Schema::create('card_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->text('note')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_workflow_events');
        Schema::table('cards', function (Blueprint $table) {
            foreach (['documenter_id', 'developer_id', 'tester_id', 'approval_owner_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropColumn(['workflow_state', 'requested_at', 'correction_origin', 'correction_note', 'demo_key']);
        });
    }
};
