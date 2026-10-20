<?php

namespace App\Tracking;

use Illuminate\Support\Collection;

class TrackingEventSchedule
{
    public static function select(Collection $events, array $codes, bool $arrival): mixed
    {

        $matches = $events->filter(fn ($event) => in_array(strtoupper((string) $event->event_code), $codes, true))->sortBy('event_date_time');

        if ($matches->isEmpty()) {

            return null;

        }

        $endpoint = $arrival ? $matches->last() : $matches->first();

        $port = $endpoint->location?->un_location_code;

        if (filled($port)) {

            $matches = $matches->filter(fn ($event) => $event->location?->un_location_code === $port);

        }

        if (! $arrival) {

            $departures = $matches->filter(fn ($event) => strtoupper((string) $event->event_code) === 'DEPA');

            if ($departures->isNotEmpty()) {

                $matches = $departures;

            }

        }

        $actual = $matches->filter(fn ($event) => strtoupper((string) $event->event_classifier_code) === 'ACT');

        $candidates = $actual->isNotEmpty() ? $actual : $matches;

        return $arrival ? $candidates->last() : $candidates->first();

    }
}
