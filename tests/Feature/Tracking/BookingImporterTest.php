<?php

namespace Tests\Feature\Tracking;

use App\Models\Carrier;
use App\Models\TrackingShipment;
use App\Tracking\BookingImporter;
use App\Tracking\OperationalBookingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BookingImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_booking_from_the_operational_source(): void
    {
        Carrier::query()->create([
            'code' => 'HAPAG_LLOYD', 'scac' => 'HLCU', 'name' => 'Hapag-Lloyd', 'connector' => 'hapag-lloyd',
        ]);
        $source = $this->mock(OperationalBookingRepository::class);
        $source->shouldReceive('candidates')->once()->with(25, null)->andReturn(new Collection([
            (object) [
                'source_id' => 991,
                'booking_reference' => '123456789',
                'transport_document_reference' => 'HLCUDE123',
                'carrier_code' => 'HLCU',
                'carrier_name' => 'Hapag-Lloyd',
            ],
        ]));

        $shipments = app(BookingImporter::class)->import(25);

        $this->assertCount(1, $shipments);
        $this->assertDatabaseHas(TrackingShipment::class, [
            'booking_reference' => '123456789',
            'transport_document_reference' => 'HLCUDE123',
            'source_id' => '991',
        ]);
    }
}
