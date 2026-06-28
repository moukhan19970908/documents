<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            // moscow | spb | sochi | other_rf | abroad — drives the per-diem / accommodation norms.
            $table->enum('location_type', ['moscow', 'spb', 'sochi', 'other_rf', 'abroad'])
                  ->nullable()
                  ->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn('location_type');
        });
    }
};
