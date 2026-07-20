<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Кому уходят порождаемые задания командировок (ТЗ 18.3) — единственная строка настроек.
        Schema::create('trip_task_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_user_id')->nullable()->constrained('users')->nullOnDelete();          // деньги
            $table->foreignId('office_manager_id')->nullable()->constrained('users')->nullOnDelete();    // тур
            $table->foreignId('logistics_id')->nullable()->constrained('users')->nullOnDelete();         // топливная карта
            $table->foreignId('transport_id')->nullable()->constrained('users')->nullOnDelete();         // служебный авто
            $table->timestamps();
        });
        DB::table('trip_task_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        // Порождаемые задания командировки (живут в «Заявках», привязаны к заявке).
        Schema::create('trip_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_request_id')->constrained('trip_requests')->cascadeOnDelete();
            $table->string('target', 20);   // money | tour | fuel_card | service_car
            $table->string('title');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('pending'); // pending | in_progress | done
            $table->text('result_comment')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
        });

        // Результат задания: файлы брони, билетов и т.п. (блок офис-менеджера).
        Schema::create('trip_task_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_task_id')->constrained('trip_tasks')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_task_files');
        Schema::dropIfExists('trip_tasks');
        Schema::dropIfExists('trip_task_settings');
    }
};
