<?php

namespace App\Tracking;

use App\Models\TrackingRawPayload;
use App\Models\TrackingShipment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MscServiceBusProcessor
{
    public function __construct(private readonly DcsaEventIngestionService $ingestion) {}

    public function process(int $id): string
    {

        return DB::transaction(function () use ($id) {

            $message = DB::table('msc_service_bus_messages')->where('id', $id)->lockForUpdate()->first();

            if (! $message || $message->processing_status !== 'PENDING') {

                return 'SKIPPED';

            }

            $event = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);

            $headers = json_decode($message->headers, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($event) || array_is_list($event) || ! is_array($headers)) {

                throw new RuntimeException('INVALID_MESSAGE');

            }

            $properties = [];

            foreach ($headers as $name => $value) {

                // REST represents string application properties as quoted HTTP header values.
                $value = is_array($value) && count($value) === 1 ? reset($value) : $value;

                if (is_string($value)) {

                    $properties[strtolower(preg_replace('/[^a-z0-9]/i', '', $name))] = trim($value, "\" ");

                }

            }

            $operation = strtoupper($properties['eventstatus'] ?? '');

            $booking = $properties['carrierbookingreference'] ?? $event['carrierBookingReference'] ?? null;

            $sourceId = $event['eventID'] ?? $event['eventId'] ?? null;

            $sequence = ltrim((string) $message->sequence_number, '0') ?: '0';

            if (! in_array($operation, ['NEW', 'UPDATE', 'DELETE'], true)
                || ! is_string($booking) || trim($booking) === ''
                || ! is_string($sourceId) || trim($sourceId) === ''
                || ! preg_match('/^\d+$/D', (string) $message->sequence_number)) {

                throw new RuntimeException('MISSING_OPERATION_BOOKING_ID_OR_SEQUENCE');

            }

            if (isset($event['carrierBookingReference']) && $event['carrierBookingReference'] !== $booking) {

                throw new RuntimeException('CONFLICTING_BOOKING');

            }

            $documents = DcsaEventFields::transportDocuments($event);

            $document = $properties['transportdocumentreference'] ?? null;

            if ($document && $documents && ! in_array($document, $documents, true)) {

                throw new RuntimeException('CONFLICTING_TRANSPORT_DOCUMENT');

            }

            if ($document && ! $documents) {

                $event['documentReferences'] = array_merge($event['documentReferences'] ?? $event['references'] ?? [], [
                    ['documentReferenceType' => 'TRD', 'documentReferenceValue' => $document],
                ]);

                $documents = [$document];

            }

            $equipment = DcsaEventFields::equipmentReference($event);

            $shipment = TrackingShipment::query()->where('booking_reference', trim($booking))
                ->whereHas('carrier', fn ($query) => $query->where('connector', 'msc'))
                ->lockForUpdate()->first();

            if (! $shipment) {

                throw new RuntimeException('SHIPMENT_NOT_FOUND');

            }

            $key = hash('sha256', json_encode([$message->source, $shipment->id, $sourceId, $equipment, $documents], JSON_THROW_ON_ERROR));

            $version = DB::table('msc_service_bus_event_versions')->where('identity_key', $key)->first();

            $older = $version && (strlen($sequence) < strlen($version->sequence_number)
                || (strlen($sequence) === strlen($version->sequence_number) && strcmp($sequence, $version->sequence_number) <= 0));

            if (! $older) {

                $matches = $shipment->events()->where('source_event_id', $sourceId)->get()->filter(function ($stored) use ($shipment, $equipment, $documents) {

                    $storedEquipment = $stored->tracking_container_id
                        ? $shipment->containers()->whereKey($stored->tracking_container_id)->value('equipment_reference') : null;

                    return $storedEquipment === $equipment
                        && DcsaEventFields::transportDocuments(['documentReferences' => $stored->references ?? []]) === $documents;

                });

                // Missing identity qualifiers must never broaden a DELETE to other BLs or equipment.
                if ($matches->count() > 1 || ($matches->isEmpty() && $shipment->events()->where('source_event_id', $sourceId)->exists()
                    && ($equipment === null || $documents === []))) {

                    throw new RuntimeException('AMBIGUOUS_EVENT_IDENTITY');

                }

                if ($operation !== 'DELETE') {

                    if (! in_array($event['eventType'] ?? null, ['EQUIPMENT', 'TRANSPORT', 'SHIPMENT'], true)
                        || DcsaEventFields::code($event) === 'UNKNOWN' || empty($event['eventDateTime'])
                        || ! in_array($event['eventClassifierCode'] ?? null, ['ACT', 'EST', 'PLN'], true)) {

                        throw new RuntimeException('INVALID_DCSA_EVENT');

                    }

                }

                $shipment->events()->whereIn('id', $matches->modelKeys())->delete();

                if ($operation !== 'DELETE') {

                    $raw = TrackingRawPayload::query()->firstOrCreate([
                        'carrier_id' => $shipment->carrier_id,
                        'checksum' => hash('sha256', 'msc-service-bus|'.$message->deduplication_key),
                    ], [
                        'tracking_shipment_id' => $shipment->id,
                        'request_reference_type' => 'BOOKING',
                        'request_reference' => $shipment->booking_reference,
                        'api_version' => '2.2',
                        'http_status' => 200,
                        'payload' => [$event],
                        'processing_status' => 'PROCESSED',
                        'received_at' => $message->received_at,
                        'processed_at' => now(),
                    ]);

                    $this->ingestion->storeEvent($shipment, $event, $raw);

                }

                DB::table('msc_service_bus_event_versions')->upsert([[
                    'identity_key' => $key,
                    'tracking_shipment_id' => $shipment->id,
                    'sequence_number' => $sequence,
                    'operation' => $operation,
                ]], ['identity_key'], ['sequence_number', 'operation']);

                $latest = $shipment->events()->where('event_classifier_code', 'ACT')->latest('event_date_time')->first();

                $shipment->update(['canonical_status' => $latest?->canonical_status ?? 'UNKNOWN']);

                // Push corrections also apply to completed shipments; polling dates remain independent.
                $shipment->stopSyncIfDestinationReached();

            }

            $status = $older ? 'IGNORED' : 'PROCESSED';

            DB::table('msc_service_bus_messages')->where('id', $id)->update([
                'processing_status' => $status,
                'processed_at' => now(),
                'processing_error' => null,
            ]);

            return $status;

        });

    }
}
