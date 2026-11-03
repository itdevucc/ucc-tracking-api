<?php

namespace App\Console\Commands;

use App\Tracking\MscServiceBusFailure;
use App\Tracking\MscServiceBusProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessMscServiceBus extends Command
{
    protected $signature = 'msc:process {--limit=100 : Máximo de mensajes pendientes}';

    protected $description = 'Aplica mensajes MSC NEW/UPDATE/DELETE ya guardados, sin conectarse a Azure';

    public function handle(MscServiceBusProcessor $processor): int
    {

        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);

        if ($limit === false) {

            $this->error('limit debe estar entre 1 y 10000.');

            return self::FAILURE;

        }

        // Rotate failed messages so an invalid batch cannot starve the rest of the inbox.
        $ids = DB::table('msc_service_bus_messages')->where('processing_status', 'PENDING')
            ->orderBy('last_attempted_at')->orderBy('id')->limit($limit)->pluck('id');

        $failures = 0;

        foreach ($ids as $id) {

            try {

                $status = $processor->process($id);

                DB::table('msc_service_bus_messages')->where('id', $id)->update(['last_attempted_at' => now()]);

                $this->info("Mensaje {$id}: {$status}.");

            } catch (Throwable $exception) {

                // Never persist exception text: SQL exceptions can contain full payloads.
                $reason = MscServiceBusFailure::describe($exception);

                DB::table('msc_service_bus_messages')->where('id', $id)->where('processing_status', 'PENDING')
                    ->update([
                        'last_attempted_at' => now(),
                        'processing_error' => $reason,
                    ]);

                $this->error("Mensaje {$id}: {$reason} Permanece PENDING.");

                $failures++;

            }

        }

        return $failures ? self::FAILURE : self::SUCCESS;

    }
}
