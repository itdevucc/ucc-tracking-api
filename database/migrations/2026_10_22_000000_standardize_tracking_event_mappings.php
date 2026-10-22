<?php

use Database\Seeders\TrackingEventMappingSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_event_mappings', function (Blueprint $table) {
            $table->string('source_empty_indicator_code', 10)->nullable();
        });

        (new TrackingEventMappingSeeder)->run();
    }

    public function down(): void
    {
        Illuminate\Support\Facades\DB::table('tracking_event_mappings')
            ->whereNull('carrier_id')->whereNull('source_classifier_code')
            ->whereNotNull('source_empty_indicator_code')->delete();

        Schema::table('tracking_event_mappings', function (Blueprint $table) {
            $table->dropColumn('source_empty_indicator_code');
        });
    }
};
