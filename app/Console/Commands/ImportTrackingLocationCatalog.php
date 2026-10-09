<?php

namespace App\Console\Commands;

use App\Models\TrackingLocationCatalog;
use Illuminate\Console\Command;
use RuntimeException;

class ImportTrackingLocationCatalog extends Command
{
    protected $signature = 'tracking:import-location-catalog
                            {file=database/data/smdg_terminal_locations.csv : Archivo CSV convertido}';

    protected $description = 'Importa el catálogo SMDG de ubicaciones para tracking';

    public function handle(): int
    {

        $path = base_path($this->argument('file'));

        $handle = fopen($path, 'r');

        if ($handle === false) {

            throw new RuntimeException("No se pudo abrir el catálogo: {$path}");

        }

        $headers = fgetcsv($handle);

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        $rows = [];

        $total = 0;

        while (($values = fgetcsv($handle)) !== false) {

            $row = array_combine($headers, $values);

            $rows[] = [
                'un_location_code' => $row['un_location_code'],
                'alternative_un_location_code' => $row['alternative_un_location_code'] ?: null,
                'terminal_code' => $row['terminal_code'],
                'name' => $row['name'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'valid_from' => $row['valid_from'],
                'valid_to' => $row['valid_to'] ?: null,
                'source_version' => $row['source_version'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) === 500) {

                $total += $this->saveRows($rows);

                $rows = [];

            }

        }

        fclose($handle);

        if ($rows !== []) {

            $total += $this->saveRows($rows);

        }

        $this->info("Ubicaciones importadas o actualizadas: {$total}");

        return self::SUCCESS;

    }

    private function saveRows(array $rows): int
    {

        TrackingLocationCatalog::query()->upsert(
            $rows,
            ['un_location_code', 'terminal_code', 'valid_from'],
            [
                'alternative_un_location_code',
                'name',
                'latitude',
                'longitude',
                'valid_to',
                'source_version',
                'updated_at',
            ],
        );

        return count($rows);

    }
}
