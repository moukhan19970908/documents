<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Частичный возврат (ТЗ 18.1 п.12): конкретная заявка выпадает из реестра,
        // остальные идут дальше. Позиция помечается «выбывшей» с автором и комментарием.
        Schema::table('registry_items', function (Blueprint $table) {
            $table->string('status', 12)->default('active')->after('vacation_request_id'); // active | dropped
            $table->foreignId('dropped_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('drop_comment')->nullable()->after('dropped_by');
            $table->timestamp('dropped_at')->nullable()->after('drop_comment');
        });
    }

    public function down(): void
    {
        Schema::table('registry_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dropped_by');
            $table->dropColumn(['status', 'drop_comment', 'dropped_at']);
        });
    }
};
