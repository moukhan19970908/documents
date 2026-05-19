<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('request_type', ['trip', 'vacation']);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->timestamps();

            $table->index(['request_type', 'department_id', 'is_active']);
        });

        Schema::create('approval_route_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedTinyInteger('approver_role_level')->nullable();
            $table->unsignedBigInteger('approver_user_id')->nullable();
            $table->foreign('route_id')->references('id')->on('approval_routes')->cascadeOnDelete();
            $table->foreign('approver_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['route_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_route_steps');
        Schema::dropIfExists('approval_routes');
    }
};
