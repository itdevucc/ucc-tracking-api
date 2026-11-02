<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tracking:import-bookings --limit=1000')->everyThirtyMinutes()->withoutOverlapping();

// Despacha únicamente shipments cuyo intervalo de consulta ya venció.
Schedule::command('tracking:dispatch')->everyTenMinutes()->withoutOverlapping();

// Vincula ubicaciones pendientes con el catálogo.
Schedule::command('tracking:link-location-catalog')->daily()->withoutOverlapping();

// Aplica los mensajes recibidos por el servicio msc:consume.
Schedule::command('msc:process --limit=1000')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/msc-service-bus-process.log'));
