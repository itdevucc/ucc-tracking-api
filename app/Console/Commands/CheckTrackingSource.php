<?php

namespace App\Console\Commands;

use App\Tracking\OperationalBookingRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckTrackingSource extends Command
{
    protected $signature = 'tracking:source-check';

    protected $description = 'Valida la conexión de solo lectura usada para obtener bookings de Sailor';

    public function handle(OperationalBookingRepository $bookings): int
    {
        $connection = config('tracking.source.connection');

        try {
            $database = DB::connection($connection)->getDatabaseName();
            $readOnly = DB::connection($connection)
                ->selectOne('SELECT @@session.tx_read_only AS enabled')
                ->enabled;

            // También ejecuta las protecciones de base autorizada y valida la consulta real.
            $bookings->candidates(1);
        } catch (Throwable $exception) {
            $this->error('La conexión de bookings no es válida: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(['Comprobación', 'Resultado'], [
            ['Conexión', $connection],
            ['Base de datos', $database],
            ['Sesión de solo lectura', (bool) $readOnly ? 'Sí' : 'No'],
            ['Consulta de bookings', 'Correcta'],
        ]);

        if (! $readOnly) {
            $this->error('La sesión no está configurada en modo de solo lectura.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
