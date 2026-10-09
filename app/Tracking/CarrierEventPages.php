<?php

namespace App\Tracking;

use App\Data\CarrierTrackingResponse;
use Illuminate\Http\Client\Response;
use UnexpectedValueException;

class CarrierEventPages
{
    /** @param callable(?string): Response $requestPage */
    public static function fetch(callable $requestPage, string $defaultVersion): CarrierTrackingResponse
    {
        $events = [];
        $cursor = null;
        $seen = [];
        $version = $defaultVersion;

        do {
            $response = $requestPage($cursor);
            $response->throw();
            if (! in_array($response->status(), [200, 204], true)) {
                throw new UnexpectedValueException('Respuesta de eventos incompleta o inesperada.');
            }
            $version = $response->header('API-Version') ?: $version;
            $page = $response->status() === 204 ? [] : $response->json();
            if (! is_array($page) || ! array_is_list($page)) {
                throw new UnexpectedValueException('La naviera no devolvió una lista de eventos válida.');
            }
            foreach ($page as $event) {
                if (! is_array($event) || ! is_string($event['eventDateTime'] ?? null) || ! filled($event['eventDateTime']) || ! filled($event['eventType'] ?? null)) {
                    throw new UnexpectedValueException('La naviera devolvió un evento incompleto.');
                }
                // Validar antes de entregar el snapshot a la persistencia.
                new \DateTimeImmutable($event['eventDateTime']);
                $events[] = $event;
            }
            $cursor = trim((string) $response->header('Next-Page-Cursor')) ?: null;
            if ($cursor !== null) {
                if (isset($seen[$cursor]) || count($seen) >= 1000 || $page === []) {
                    throw new UnexpectedValueException('La paginación de eventos no pudo completarse.');
                }
                $seen[$cursor] = true;
            }
        } while ($cursor !== null);

        return new CarrierTrackingResponse($events, $events === [] ? $response->status() : 200, $version);
    }
}
