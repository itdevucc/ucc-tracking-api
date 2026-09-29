<?php

namespace App\Data;

class CarrierTrackingResponse
{
    /** @param array<int, array<string, mixed>> $events */
    public function __construct(
        public readonly array $events,
        public readonly int $httpStatus,
        public readonly ?string $apiVersion = null,
    ) {}
}
