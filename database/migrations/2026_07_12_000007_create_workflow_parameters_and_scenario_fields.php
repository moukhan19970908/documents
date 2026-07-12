<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('process_type')->nullable()->after('description');  // документооборот, приказы, процедуры…
            $table->string('icon')->nullable()->after('process_type');
            $table->foreignId('owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'published'])->default('published')->after('is_active');
        });

        /**
         * Launch parameters — the questions asked when a document is created. Their answers
         * decide which stages enter the route. Deliberately NOT document_fields: those describe
         * the document (filters, archive, naming); these shape the route.
         */
        Schema::create('workflow_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label');
            $table->enum('type', ['select', 'radio', 'boolean', 'number', 'reference', 'date'])->default('select');
            $table->json('options')->nullable();          // [{value, label}, …]
            $table->boolean('is_required')->default(false);
            $table->string('default_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workflow_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_parameters');

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['process_type', 'icon', 'owner_id', 'status']);
        });
    }
};
