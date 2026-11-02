<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::table('msc_service_bus_messages', function (Blueprint $table) {

            $table->timestamp('processed_at')->nullable();

            $table->string('processing_error')->nullable();

        });

        Schema::create('msc_service_bus_event_versions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('tracking_shipment_id')->constrained()->cascadeOnDelete();

            $table->char('identity_key', 64)->unique();

            $table->string('sequence_number');

            $table->string('operation', 10);

        });

    }

    public function down(): void
    {

        Schema::dropIfExists('msc_service_bus_event_versions');

        Schema::table('msc_service_bus_messages', function (Blueprint $table) {

            $table->dropColumn(['processed_at', 'processing_error']);

        });

    }
};
