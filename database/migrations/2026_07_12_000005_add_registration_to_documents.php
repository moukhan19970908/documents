<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('document_subtype_id')->nullable()->after('document_type_id')
                ->constrained('document_subtypes')->nullOnDelete();
            $table->string('number')->nullable()->after('title')->index();
            $table->timestamp('registered_at')->nullable()->after('number');
        });

        Schema::create('document_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('numerator_id')->nullable()->constrained('numerators')->nullOnDelete();
            $table->string('scope_key')->nullable();
            $table->string('number');
            $table->timestamp('registered_at');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();

            $table->index('number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_registrations');

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['document_subtype_id']);
            $table->dropColumn(['document_subtype_id', 'number', 'registered_at']);
        });
    }
};
