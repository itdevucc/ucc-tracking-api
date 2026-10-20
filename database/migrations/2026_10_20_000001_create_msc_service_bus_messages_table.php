<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {

        Schema::create('msc_service_bus_messages', function (Blueprint $table) {

            $table->id();

            $table->char('deduplication_key', 64)->unique();

            $table->text('source');

            $table->string('sequence_number')->nullable();

            $table->text('message_id')->nullable();

            $table->longText('body');

            $table->json('headers');

            $table->json('broker_properties');

            $table->string('processing_status')->default('PENDING')->index();

            $table->timestamp('received_at');

            $table->timestamp('acknowledged_at')->nullable();

        });

    }

    public function down(): void
    {

        Schema::dropIfExists('msc_service_bus_messages');

    }

};
