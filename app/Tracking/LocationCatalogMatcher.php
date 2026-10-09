<?php

namespace App\Tracking;

use App\Models\TrackingLocationCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class LocationCatalogMatcher
{
    public function find(
        ?string $unLocationCode,
        ?string $terminalCode = null,
        ?CarbonInterface $at = null,
        ?string $locationName = null,
    ): ?TrackingLocationCatalog
    {

        $unLocationCode = strtoupper(trim((string) $unLocationCode));

        $terminalCode = strtoupper(trim((string) $terminalCode));

        $date = ($at ?? now())->toDateString();

        if ($unLocationCode === '') {

            return $this->findByName($locationName, $date);

        }

        $query = TrackingLocationCatalog::query()
            ->where(function (Builder $query) use ($unLocationCode) {

                $query->where('un_location_code', $unLocationCode)
                    ->orWhere('alternative_un_location_code', $unLocationCode);

            })
            ->whereDate('valid_from', '<=', $date)
            ->where(function (Builder $query) use ($date) {

                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);

            });

        if ($terminalCode !== '') {

            return $query->where('terminal_code', $terminalCode)
                ->latest('valid_from')
                ->first();

        }

        $matches = $query->get();

        if ($matches->count() <= 1) {

            return $matches->first();

        }

        $latitude = $matches->avg('latitude');

        $longitude = $matches->avg('longitude');

        return $matches->sortBy(fn ($location) =>
            (($location->latitude - $latitude) ** 2) + (($location->longitude - $longitude) ** 2)
        )->first();

    }

    private function findByName(?string $locationName, string $date): ?TrackingLocationCatalog
    {

        $locationName = trim((string) $locationName);

        if ($locationName === '') {

            return null;

        }

        $matches = TrackingLocationCatalog::query()
            ->where('name', 'like', "%{$locationName}%")
            ->whereDate('valid_from', '<=', $date)
            ->where(fn (Builder $query) =>
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date)
            )
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;

    }
}
