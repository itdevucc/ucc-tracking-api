<?php

namespace App\Console\Commands;

use App\Models\TrackingLocation;
use App\Tracking\LocationCatalogMatcher;
use Illuminate\Console\Command;

class LinkTrackingLocationsToCatalog extends Command
{
    protected $signature = 'tracking:link-location-catalog';

    protected $description = 'Relaciona las ubicaciones de tracking existentes con el catálogo SMDG';

    public function handle(LocationCatalogMatcher $matcher): int
    {

        $linked = 0;

        TrackingLocation::query()
            ->whereNull('tracking_location_catalog_id')
            ->chunkById(500, function ($locations) use ($matcher, &$linked) {

                foreach ($locations as $location) {

                    $catalog = $matcher->find(
                        $location->un_location_code,
                        $location->facility_smdg_code,
                        locationName: $location->name,
                    );

                    if (! $catalog) {

                        continue;

                    }

                    $location->update([
                        'tracking_location_catalog_id' => $catalog->id,
                        'un_location_code' => $location->un_location_code ?: $catalog->un_location_code,
                        'country_code' => $location->country_code ?: substr($catalog->un_location_code, 0, 2),
                        'latitude' => $catalog->latitude,
                        'longitude' => $catalog->longitude,
                    ]);

                    $linked++;

                }

            });

        $this->info("Ubicaciones relacionadas: {$linked}");

        return self::SUCCESS;

    }
}
