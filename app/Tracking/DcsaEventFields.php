<?php

namespace App\Tracking;

class DcsaEventFields
{
    public static function equipmentReference(array $event): ?string
    {

        if (filled($event['equipmentReference'] ?? null)) {

            return $event['equipmentReference'];

        }

        $references = collect($event['references'] ?? [])
            ->filter(fn ($reference) => ($reference['referenceType'] ?? null) === 'EQ')
            ->pluck('referenceValue')->filter()->unique()->values();

        return $references->count() === 1 ? $references->first() : null;

    }

    public static function code(array $event): string
    {

        return match (strtoupper((string) ($event['eventType'] ?? 'UNKNOWN'))) {

            'EQUIPMENT' => $event['equipmentEventTypeCode'] ?? 'UNKNOWN',
            'TRANSPORT' => $event['transportEventTypeCode'] ?? 'UNKNOWN',
            'SHIPMENT' => $event['shipmentEventTypeCode'] ?? 'UNKNOWN',
            default => 'UNKNOWN',

        };

    }

    public static function description(array $event, ?string $fallback): ?string
    {

        foreach (['description', 'eventTypeDescription'] as $field) {

            if (is_string($event[$field] ?? null) && filled($event[$field])) {

                return $event[$field];

            }

        }

        return $fallback;

    }
}
