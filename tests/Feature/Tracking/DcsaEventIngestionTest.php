<?php

namespace Tests\Feature\Tracking;

use App\Data\CarrierTrackingResponse;
use App\Models\Carrier;
use App\Models\TrackingShipment;
use App\Tracking\DcsaEventIngestionService;
use Database\Seeders\TrackingReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DcsaEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalizes_dcsa_events_idempotently(): void
    {
        $this->seed(TrackingReferenceSeeder::class);
        $carrier = Carrier::query()->where('code', 'HAPAG_LLOYD')->firstOrFail();
        $shipment = TrackingShipment::query()->create([
            'carrier_id' => $carrier->id,
            'booking_reference' => '123456789',
        ]);
        $response = new CarrierTrackingResponse([
            [
                'eventCreatedDateTime' => '2026-09-28T11:00:00Z',
                'eventType' => 'EQUIPMENT',
                'eventClassifierCode' => 'ACT',
                'eventDateTime' => '2026-09-28T10:00:00Z',
                'equipmentEventTypeCode' => 'LOAD',
                'equipmentReference' => 'HLCU1234567',
                'ISOEquipmentCode' => '22GP',
                'emptyIndicatorCode' => 'LADEN',
                'eventLocation' => ['UNLocationCode' => 'CLVAP', 'locationName' => 'Valparaiso'],
            ],
            [
                'eventCreatedDateTime' => '2026-09-28T12:00:00Z',
                'eventType' => 'TRANSPORT',
                'eventClassifierCode' => 'ACT',
                'eventDateTime' => '2026-09-28T12:00:00Z',
                'transportEventTypeCode' => 'DEPA',
                'transportCall' => [
                    'transportCallID' => 'call-1',
                    'modeOfTransport' => 'VESSEL',
                    'UNLocationCode' => 'CLVAP',
                    'location' => ['UNLocationCode' => 'CLVAP', 'locationName' => 'Valparaiso'],
                    'vessel' => ['vesselIMONumber' => '9876543', 'vesselName' => 'Test Vessel'],
                ],
            ],
        ], 200, '2.3.3');

        $service = app(DcsaEventIngestionService::class);
        $first = $service->ingest($shipment, $response);
        $second = $service->ingest($shipment->fresh(), $response);

        $this->assertSame(['created' => 2, 'updated' => 0], $first);
        $this->assertSame(['created' => 0, 'updated' => 0], $second);
        $this->assertDatabaseCount('tracking_events', 2);
        $this->assertDatabaseCount('tracking_containers', 1);
        $this->assertDatabaseCount('tracking_raw_payloads', 1);
        $this->assertDatabaseHas('tracking_shipments', ['id' => $shipment->id, 'canonical_status' => 'DEPARTED']);
    }
}
