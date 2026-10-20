<?php

namespace App\Tracking;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class MscServiceBusInbox
{

    public function store(string $source, array $message): int
    {

        $sequence = $message['broker']['SequenceNumber'] ?? null;

        if ($sequence === null) {

            throw new RuntimeException('Mensaje sin SequenceNumber: no se confirmó su recepción.');

        }

        // SequenceNumber identifies a queued message, including redeliveries after a lost ACK.
        $key = hash('sha256', $source.'|'.$sequence);

        return DB::transaction(function () use ($source, $message, $sequence, $key) {

            DB::table('msc_service_bus_messages')->upsert([[
                'deduplication_key' => $key,
                'source' => $source,
                'sequence_number' => (string) $sequence,
                'message_id' => $message['broker']['MessageId'] ?? null,
                'body' => $message['body'],
                'headers' => json_encode($message['headers'], JSON_THROW_ON_ERROR),
                'broker_properties' => json_encode($message['broker'], JSON_THROW_ON_ERROR),
                'processing_status' => 'PENDING',
                'received_at' => now(),
            ]], ['deduplication_key'], ['deduplication_key']);

            return (int) DB::table('msc_service_bus_messages')->where('deduplication_key', $key)->value('id');

        });

    }

}
