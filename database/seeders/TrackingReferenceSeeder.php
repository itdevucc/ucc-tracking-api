<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\TrackingEventMapping;
use Illuminate\Database\Seeder;

class TrackingReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Carrier::query()->updateOrCreate(
            ['code' => 'MSC'],
            ['scac' => 'MSCU', 'name' => 'Mediterranean Shipping Company', 'connector' => 'msc', 'tracking_api_version' => '2.2', 'is_active' => true],
        );
        Carrier::query()->updateOrCreate(
            ['code' => 'HAPAG_LLOYD'],
            ['scac' => 'HLCU', 'name' => 'Hapag-Lloyd', 'connector' => 'hapag-lloyd', 'tracking_api_version' => '2.3.3', 'is_active' => true],
        );

        $mappings = [
            ['EQUIPMENT', 'LOAD', 'LOADED', 'Cargado', 40],
            ['EQUIPMENT', 'DISC', 'DISCHARGED', 'Descargado', 70],
            ['EQUIPMENT', 'GTIN', 'GATE_IN', 'Ingreso a terminal', 30],
            ['EQUIPMENT', 'GTOT', 'GATE_OUT', 'Salida de terminal', 80],
            ['EQUIPMENT', 'STUF', 'STUFFED', 'Contenedor llenado', 20],
            ['EQUIPMENT', 'STRP', 'STRIPPED', 'Contenedor vaciado', 90],
            ['TRANSPORT', 'DEPA', 'DEPARTED', 'Transporte zarpó', 50],
            ['TRANSPORT', 'ARRI', 'ARRIVED', 'Transporte arribó', 60],
            ['SHIPMENT', 'CONF', 'BOOKED', 'Booking confirmado', 10],
            ['SHIPMENT', 'ISSU', 'DOCUMENT_ISSUED', 'Documento emitido', 15],
        ];

        foreach ($mappings as [$type, $code, $status, $label, $order]) {
            TrackingEventMapping::query()->updateOrCreate(
                ['carrier_id' => null, 'source_event_type' => $type, 'source_event_code' => $code, 'source_classifier_code' => null],
                ['canonical_status' => $status, 'label_es' => $label, 'sort_order' => $order],
            );
        }
    }
}
