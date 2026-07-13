<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Какие бланки сценарий предлагает сотруднику. Пусто — сценарий работает по-старому:
        // документ приносят готовым файлом.
        Schema::create('blank_template_workflow', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('blank_template_id')->constrained('blank_templates')->cascadeOnDelete();

            $table->unique(['workflow_id', 'blank_template_id']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            // Разрешена ли загрузка готового файла вместо бланка.
            $table->boolean('allow_file_upload')->default(true)->after('icon');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('blank_template_id')->nullable()->after('document_subtype_id')
                ->constrained('blank_templates')->nullOnDelete();

            // Тело документа, заполненное по бланку. Токены {номер}, {дата} и атрибуты
            // хранятся как есть и подставляются при показе — иначе номер, выданный
            // при регистрации, не дошёл бы до уже заполненного бланка.
            $table->longText('body_html')->nullable()->after('data');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['blank_template_id']);
            $table->dropColumn(['blank_template_id', 'body_html']);
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('allow_file_upload');
        });

        Schema::dropIfExists('blank_template_workflow');
    }
};
