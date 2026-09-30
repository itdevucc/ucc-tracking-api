<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_locations', function (Blueprint $table) {
            $table->foreignId('tracking_location_catalog_id')
                ->nullable()
                ->after('id')
                ->constrained('tracking_location_catalogs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracking_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracking_location_catalog_id');
        });
    }
};
