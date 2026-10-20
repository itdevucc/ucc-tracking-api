<?php

namespace App\Console\Commands;

use App\Tracking\MscServiceBusClient;
use App\Tracking\MscServiceBusInbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ConsumeMscServiceBus extends Command
{

    protected $signature = 'msc:consume {--once : Intentar recibir un solo mensaje y terminar}';

    protected $description = 'Recibe mensajes de MSC y los confirma solamente después de guardarlos en la bandeja de entrada';

    public function handle(MscServiceBusInbox $inbox): int
    {

        try {

            $client = app(MscServiceBusClient::class);

            if (! Schema::hasTable('msc_service_bus_messages')) {

                $this->error('Falta la tabla de recepción. Ejecuta primero la migración de Service Bus.');

                return self::FAILURE;

            }

        } catch (Throwable) {

            $this->error('No se pudo iniciar: revisa configuración SAS, EntityPath y conexión a la base de datos.');

            return self::FAILURE;

        }

        $running = true;

        if (extension_loaded('pcntl')) {

            $this->trap([SIGTERM, SIGINT], function () use (&$running) {

                $running = false;

            });

        }
        $this->info('Escuchando MSC. Los mensajes se guardan como PENDING; todavía no actualizan el tracking.');

        do {

            $stage = 'recepción';

            try {

                $message = $client->receive();

                if ($message !== null) {

                    $stage = 'almacenamiento';

                    $id = $inbox->store($client->source(), $message);

                    $stage = 'confirmación';

                    $client->complete($message['location']);

                    DB::table('msc_service_bus_messages')->where('id', $id)->update(['acknowledged_at' => now()]);

                    $this->info("Mensaje guardado y confirmado. ID local: {$id}.");

                } elseif ($this->option('once')) {

                    $this->info('Sin mensajes disponibles durante la espera.');

                }

            } catch (Throwable) {

                // Do not print exceptions: database exceptions may contain message payloads.
                $this->error("Falló la {$stage}. HTTP 401/403 requiere revisar permisos Listen; comprueba también red y base de datos. No se descarta el mensaje sin guardarlo.");

                if ($this->option('once')) {

                    return self::FAILURE;

                }
                sleep(5);

            }

        } while ($running && ! $this->option('once'));

        return self::SUCCESS;

    }

}
