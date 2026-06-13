<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stage_approvers', function (Blueprint $table) {
            $table->string('participant_type')->default('signatory')->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_stage_approvers', function (Blueprint $table) {
            $table->dropColumn('participant_type');
        });
    }
};
