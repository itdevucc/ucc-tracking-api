<?php

namespace App\Tracking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

class HapagRequestLimiter
{

    public function acquire(): void
    {

        $cache = Cache::store(config('tracking.hapag_lloyd.rate_limit_store'));

        $key = 'tracking:hapag:requests:'.hash('sha256', (string) config('tracking.hapag_lloyd.client_id'));

        do {

            $wait = $cache->lock($key.':lock', 10)->block(5, function () use ($cache, $key): int {

                $now = now()->getTimestampMs();

                $requests = array_values(array_filter(
                    $cache->get($key, []),
                    fn (int $timestamp): bool => $timestamp > $now - 60000,
                ));

                if (count($requests) >= 10) {

                    return max(1, $requests[0] + 60000 - $now);

                }

                $requests[] = $now;

                $cache->put($key, $requests, 120);

                return 0;

            });

            if ($wait > 0) {

                // Esperar fuera del bloqueo permite coordinar varios workers.
                Sleep::for($wait)->milliseconds();

            }

        } while ($wait > 0);

    }

}
