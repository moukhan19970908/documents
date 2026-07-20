<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Единый сквозной счётчик на все привязанные классификаторы (true)
        // либо отдельная последовательность для каждого классификатора (false, по умолчанию).
        Schema::table('numerators', function (Blueprint $table) {
            $table->boolean('shared_counter')->default(false)->after('scope');
        });

        // Привязка нумератора к классификатору: тип/подтип документа или вид приказа.
        // Один классификатор — не более одного нумератора; один нумератор — много классификаторов (1:N).
        Schema::create('numerator_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('numerator_id')->constrained('numerators')->cascadeOnDelete();
            $table->string('classifier_type', 20);   // document_type / document_subtype / order_kind
            $table->string('classifier_id', 40);     // id типа/подтипа или код вида приказа (operational…)
            $table->timestamps();

            $table->unique(['classifier_type', 'classifier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numerator_bindings');

        Schema::table('numerators', function (Blueprint $table) {
            $table->dropColumn('shared_counter');
        });
    }
};
