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

    public static function transportDocuments(array $event): array
    {

        $references = $event['documentReferences'] ?? $event['references'] ?? [];

        if ($references && ! array_is_list($references)) {

            $references = [$references];

        }

        $documents = [];

        foreach ($references as $reference) {

            $type = strtoupper((string) ($reference['documentReferenceType'] ?? $reference['referenceType'] ?? $reference['type'] ?? ''));

            $value = $reference['documentReferenceValue'] ?? $reference['referenceValue'] ?? $reference['value'] ?? null;

            if (in_array($type, ['TRD', 'BOL', 'BL'], true) && filled($value)) {

                $documents[] = (string) $value;

            }

        }

        $documents = array_values(array_unique($documents));

        sort($documents, SORT_STRING);

        return $documents;

    }

    public static function location(array $event): array
    {

        $call = is_array($event['transportCall'] ?? null) ? $event['transportCall'] : [];

        $nested = is_array($call['location'] ?? null) ? $call['location'] : [];

        $explicit = is_array($event['eventLocation'] ?? null) ? $event['eventLocation'] : [];

        $data = array_replace($nested, $explicit);

        $port = '';

        foreach ([$data, $event, $call] as $source) {

            $candidate = $source['UNLocationCode'] ?? $source['unLocationCode'] ?? '';

            if (filled($candidate)) {

                $port = strtoupper(trim((string) $candidate));

                break;

            }

        }

        $terminal = data_get($data, 'facility.SMDGCode');

        foreach ([$explicit, $nested, $call] as $source) {

            if (! filled($terminal) && strtoupper((string) ($source['facilityCodeListProvider'] ?? '')) === 'SMDG') {

                $terminal = $source['facilityCode'] ?? null;

            }

        }

        return [
            'un_location_code' => $port,
            'facility_smdg_code' => filled($terminal) ? strtoupper(trim((string) $terminal)) : null,
            'name' => trim((string) ($data['locationName'] ?? $call['locationName'] ?? '')),
            'country_code' => $data['countryCode'] ?? $call['countryCode'] ?? ($port ? substr($port, 0, 2) : null),
        ];

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
