<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('route_id')->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'revision', 'in_registry'])->default('draft');
            $table->string('city');
            $table->text('purpose');
            $table->date('date_start');
            $table->date('date_end');
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('accommodation_total', 10, 2)->default(0);
            $table->decimal('transport_total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->text('comment')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('route_id')->references('id')->on('approval_routes')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });

        Schema::create('vacation_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('route_id')->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'revision'])->default('draft');
            $table->enum('vacation_type', ['annual', 'unpaid', 'sick_leave', 'other'])->default('annual');
            $table->date('date_start');
            $table->date('date_end');
            $table->unsignedSmallInteger('days_count')->default(0);
            $table->text('comment')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('route_id')->references('id')->on('approval_routes')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacation_requests');
        Schema::dropIfExists('trip_requests');
    }
};
