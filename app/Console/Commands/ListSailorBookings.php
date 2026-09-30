<?php

namespace App\Console\Commands;

use App\Tracking\OperationalBookingRepository;
use Illuminate\Console\Command;

class ListSailorBookings extends Command
{
    protected $signature = 'tracking:source-bookings {--limit=20 : Cantidad de bookings a mostrar (1-100)}';

    protected $description = 'Consulta bookings confirmados o embarcados de Hapag-Lloyd y MSC desde Sailor';

    public function handle(OperationalBookingRepository $bookings): int
    {

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => 100
            ],
        ]);

        if ($limit === false) {

            $this->error('El límite debe ser un número entre 1 y 100.');

            return self::INVALID;

        }

        $total = $bookings->countCandidates();

        $totalsByState = $bookings->countCandidatesByState();

        $rows = $bookings->candidates($limit);

        $this->info("Bookings encontrados: {$total}. Mostrando hasta {$limit}.");

        $this->table(['ID Estado', 'Estado', 'Código', 'Cantidad'],
            $totalsByState->map(fn (object $state): array => [
                $state->state_id,
                $state->state_name,
                $state->state_code,
                $state->total,
            ])->all()
        );

        $this->table(['ID Sailor', 'Booking', 'Naviera', 'Estado', 'Fecha salida'],
            $rows->map(fn (object $booking): array => [
                $booking->source_id,
                $booking->booking_reference,
                $booking->carrier_code,
                $booking->booking_state_code,
                $booking->source_date,
            ])->all()
        );

        return self::SUCCESS;
    }
}
