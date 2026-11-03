<?php

namespace App\Tracking;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\QueryException;
use JsonException;
use RuntimeException;
use Throwable;

class MscServiceBusFailure
{
    public static function describe(Throwable $exception): string
    {

        $reasons = [
            'INVALID_MESSAGE' => 'El cuerpo debe contener un evento o una lista no vacía de eventos DCSA.',
            'INVALID_EVENT_STATUS' => 'Falta la propiedad EventStatus o no contiene NEW, UPDATE o DELETE.',
            'MISSING_BOOKING' => 'Falta CarrierBookingReference o una referencia BKG única.',
            'MISSING_EVENT_ID' => 'Falta eventID/eventId en el evento.',
            'INVALID_SEQUENCE' => 'Falta SequenceNumber válido en la copia local.',
            'CONFLICTING_BOOKING' => 'El booking del cuerpo no coincide con el de las propiedades.',
            'CONFLICTING_TRANSPORT_DOCUMENT' => 'El BL del cuerpo no coincide con el de las propiedades.',
            'SHIPMENT_NOT_FOUND' => 'No existe un shipment MSC local para el booking del mensaje.',
            'AMBIGUOUS_EVENT_IDENTITY' => 'No se puede identificar un único evento por ID, equipo y BL.',
            'INVALID_DCSA_EVENT' => 'Falta tipo, código, clasificador o fecha válida del evento DCSA.',
        ];

        if ($exception instanceof RuntimeException && isset($reasons[$exception->getMessage()])) {

            return $exception->getMessage().': '.$reasons[$exception->getMessage()];

        }

        if ($exception instanceof JsonException) {

            return 'INVALID_JSON: El cuerpo o los headers guardados no contienen JSON válido.';

        }

        if ($exception instanceof InvalidFormatException) {

            return 'INVALID_DATE: No se pudo interpretar una fecha del evento.';

        }

        if ($exception instanceof QueryException) {

            // SQLSTATE identifies the error category without exposing SQL or bindings.
            $state = (string) $exception->getCode();

            return 'DATABASE_ERROR'.(preg_match('/^[A-Z0-9]{5}$/D', $state) ? ' ['.$state.']' : '').': Revisar esquema, permisos y conexión.';

        }

        return 'PROCESSING_ERROR: Fallo interno al interpretar o guardar el evento; no se aplicó la transacción.';

    }
}
