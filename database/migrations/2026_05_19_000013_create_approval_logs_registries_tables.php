<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_approval_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('request_type', ['trip', 'vacation', 'registry']);
            $table->unsignedBigInteger('request_id');
            $table->unsignedTinyInteger('step_number')->default(1);
            $table->unsignedBigInteger('approver_id');
            $table->enum('action', ['approved', 'rejected', 'sent_revision', 'reassigned', 'submitted']);
            $table->text('comment')->nullable();
            $table->foreign('approver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['request_type', 'request_id']);
        });

        Schema::create('registries', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['trip', 'vacation']);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('route_id')->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'sent_to_accounting', 'accepted_by_accounting'])->default('draft');
            $table->string('title');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('comment')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('route_id')->references('id')->on('approval_routes')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'status', 'created_at']);
        });

        Schema::create('registry_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('registry_id');
            $table->unsignedBigInteger('trip_request_id')->nullable();
            $table->unsignedBigInteger('vacation_request_id')->nullable();
            $table->foreign('registry_id')->references('id')->on('registries')->cascadeOnDelete();
            $table->foreign('trip_request_id')->references('id')->on('trip_requests')->nullOnDelete();
            $table->foreign('vacation_request_id')->references('id')->on('vacation_requests')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registry_items');
        Schema::dropIfExists('registries');
        Schema::dropIfExists('trip_approval_logs');
    }
};
