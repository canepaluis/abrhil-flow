<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Team profiles can exist before email and sign-in credentials are provided.
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('profile_key')->nullable()->unique();
            $table->json('responsibilities')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        // Keep email/password nullable: existing profiles may have no credentials.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['profile_key']);
            $table->dropColumn(['profile_key', 'responsibilities']);
        });
    }
};
