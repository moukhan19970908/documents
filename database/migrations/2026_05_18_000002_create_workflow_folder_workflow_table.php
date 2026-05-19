<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_folder_workflow', function (Blueprint $table) {
            $table->unsignedBigInteger('workflow_folder_id');
            $table->unsignedBigInteger('workflow_id');

            $table->primary(['workflow_folder_id', 'workflow_id']);
            $table->foreign('workflow_folder_id')->references('id')->on('workflow_folders')->onDelete('cascade');
            $table->foreign('workflow_id')->references('id')->on('workflows')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_folder_workflow');
    }
};
