<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('icon', 30)->default('user');
            $table->string('color', 20)->default('indigo');
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['role_id', 'user_id']);
        });

        // users.role stays the primary role, but is no longer limited to the
        // five hardcoded enum values — any role code may now be primary.
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('linear')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'director', 'linear', 'archiver', 'external'])->default('linear')->change();
        });
    }
};
