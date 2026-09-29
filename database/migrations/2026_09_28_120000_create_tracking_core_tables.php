<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('scac', 10)->nullable()->index();
            $table->string('name');
            $table->string('connector');
            $table->string('tracking_api_version', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('settings')->nullable()->comment('Configuración no sensible; las credenciales van en variables de entorno');
            $table->timestamps();
        });

        Schema::create('tracking_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->string('booking_reference', 100);
            $table->string('transport_document_reference', 100)->nullable()->index();
            $table->string('source_system', 100)->nullable();
            $table->string('source_table', 100)->nullable();
            $table->string('source_id', 100)->nullable();
            $table->string('canonical_status', 50)->default('UNKNOWN')->index();
            $table->boolean('sync_enabled')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('next_sync_at')->nullable()->index();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['carrier_id', 'booking_reference']);
            $table->index(['source_system', 'source_table', 'source_id'], 'tracking_shipments_source_index');
        });

        Schema::create('tracking_locations', function (Blueprint $table) {
            $table->id();
            $table->char('un_location_code', 5)->nullable()->index();
            $table->string('facility_smdg_code', 20)->nullable();
            $table->string('facility_type_code', 10)->nullable();
            $table->string('name')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->timestamps();

            $table->index(['un_location_code', 'facility_smdg_code'], 'tracking_locations_lookup_index');
        });

        Schema::create('tracking_containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_shipment_id')->constrained('tracking_shipments')->cascadeOnDelete();
            $table->string('equipment_reference', 20);
            $table->string('iso_equipment_code', 10)->nullable();
            $table->string('empty_indicator_code', 20)->nullable();
            $table->string('seal_reference', 100)->nullable();
            $table->string('canonical_status', 50)->default('UNKNOWN')->index();
            $table->timestamps();

            $table->unique(['tracking_shipment_id', 'equipment_reference'], 'tracking_containers_shipment_equipment_unique');
            $table->index('equipment_reference');
        });

        Schema::create('tracking_transport_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_shipment_id')->constrained('tracking_shipments')->cascadeOnDelete();
            $table->foreignId('tracking_location_id')->nullable()->constrained('tracking_locations')->nullOnDelete();
            $table->string('external_transport_call_id', 100)->nullable();
            $table->string('mode_of_transport', 30)->nullable();
            $table->string('vessel_imo_number', 10)->nullable()->index();
            $table->string('vessel_name')->nullable();
            $table->string('export_voyage_number', 100)->nullable();
            $table->string('import_voyage_number', 100)->nullable();
            $table->string('carrier_service_code', 100)->nullable();
            $table->string('universal_service_reference', 100)->nullable();
            $table->timestamps();

            $table->index(['tracking_shipment_id', 'external_transport_call_id'], 'tracking_transport_calls_external_index');
        });

        Schema::create('tracking_raw_payloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('tracking_shipment_id')->nullable()->constrained('tracking_shipments')->nullOnDelete();
            $table->string('request_reference_type', 30);
            $table->string('request_reference', 100);
            $table->string('api_version', 20)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('checksum', 64);
            $table->json('payload');
            $table->string('processing_status', 30)->default('PENDING')->index();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['carrier_id', 'checksum']);
            $table->index(['request_reference_type', 'request_reference'], 'tracking_raw_payloads_reference_index');
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_shipment_id')->constrained('tracking_shipments')->cascadeOnDelete();
            $table->foreignId('tracking_container_id')->nullable()->constrained('tracking_containers')->nullOnDelete();
            $table->foreignId('tracking_transport_call_id')->nullable()->constrained('tracking_transport_calls')->nullOnDelete();
            $table->foreignId('tracking_location_id')->nullable()->constrained('tracking_locations')->nullOnDelete();
            $table->foreignId('tracking_raw_payload_id')->nullable()->constrained('tracking_raw_payloads')->nullOnDelete();
            $table->string('source_event_id', 100)->nullable();
            $table->string('fingerprint', 64);
            $table->string('event_type', 30)->index();
            $table->string('event_classifier_code', 10)->nullable()->index();
            $table->string('event_code', 50)->index();
            $table->string('canonical_status', 50)->default('UNKNOWN')->index();
            $table->timestamp('event_date_time')->index();
            $table->timestamp('event_created_date_time')->nullable();
            $table->string('document_type_code', 20)->nullable();
            $table->string('document_id', 100)->nullable();
            $table->text('description')->nullable();
            $table->json('references')->nullable();
            $table->timestamps();

            $table->unique(['tracking_shipment_id', 'fingerprint'], 'tracking_events_shipment_fingerprint_unique');
        });

        Schema::create('tracking_event_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source_event_type', 30);
            $table->string('source_event_code', 50);
            $table->string('source_classifier_code', 10)->nullable();
            $table->string('canonical_status', 50)->index();
            $table->string('label_es');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();

            $table->index(['carrier_id', 'source_event_type', 'source_event_code'], 'tracking_event_mappings_lookup_index');
        });

        Schema::create('tracking_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrier_id')->constrained()->restrictOnDelete();
            $table->foreignId('tracking_shipment_id')->nullable()->constrained('tracking_shipments')->nullOnDelete();
            $table->string('trigger', 30)->default('SCHEDULED');
            $table->string('status', 30)->default('RUNNING')->index();
            $table->json('request_parameters')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('payload_count')->default(0);
            $table->unsignedInteger('events_created')->default(0);
            $table->unsignedInteger('events_updated')->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_sync_runs');
        Schema::dropIfExists('tracking_event_mappings');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('tracking_raw_payloads');
        Schema::dropIfExists('tracking_transport_calls');
        Schema::dropIfExists('tracking_containers');
        Schema::dropIfExists('tracking_locations');
        Schema::dropIfExists('tracking_shipments');
        Schema::dropIfExists('carriers');
    }
};
