<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tracking_location_catalogs')->upsert([
            [
                'un_location_code' => 'DEMUC',
                'alternative_un_location_code' => null,
                'terminal_code' => 'UNLOC',
                'name' => 'Munich',
                'latitude' => 48.1500000,
                'longitude' => 11.5833333,
                'valid_from' => '1900-01-01',
                'valid_to' => null,
                'source_version' => 'UNECE-UNLOCODE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'un_location_code' => 'DEZAV',
                'alternative_un_location_code' => null,
                'terminal_code' => 'UNLOC',
                'name' => 'Loiching',
                'latitude' => 48.6166667,
                'longitude' => 12.4333333,
                'valid_from' => '1900-01-01',
                'valid_to' => null,
                'source_version' => 'UNECE-UNLOCODE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['un_location_code', 'terminal_code', 'valid_from'], [
            'name',
            'latitude',
            'longitude',
            'source_version',
            'updated_at',
        ]);
    }

    public function down(): void
    {
        DB::table('tracking_location_catalogs')
            ->where('source_version', 'UNECE-UNLOCODE')
            ->whereIn('un_location_code', ['DEMUC', 'DEZAV'])
            ->delete();
    }
};
