<?php

namespace App\Tracking;

use Illuminate\Support\Facades\Cache;

class MscRequestLimiter
{
    public function acquire(): void
    {
        $cache = Cache::store(config('tracking.msc.rate_limit_store'));

        $key = 'tracking:msc:request-delay:'.hash('sha256', (string) config('tracking.msc.client_id'));

        $cache->lock($key.':lock', 10)->block(5, function () use ($cache, $key) {

            $next = (float) $cache->get($key, 0);

            while (($wait = $next - microtime(true)) > 0) {

                usleep((int) ceil($wait * 1000000));

            }

            $delay = max(300, min(1000, (int) config('tracking.msc.request_delay_ms', 300)));

            $cache->put($key, microtime(true) + $delay / 1000, 60);

        });

    }

}
