<?php

namespace App\Tracking;

use App\Contracts\CarrierTrackingConnector;
use InvalidArgumentException;

class CarrierConnectorManager
{
    /** @param iterable<CarrierTrackingConnector> $connectors */
    public function __construct(private readonly iterable $connectors) {}

    public function for(string $key): CarrierTrackingConnector
    {
        foreach ($this->connectors as $connector) {
            if ($connector->key() === $key) {
                return $connector;
            }
        }

        throw new InvalidArgumentException("No existe conector de tracking [{$key}].");
    }
}
