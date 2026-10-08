<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {

    $this->comment(Inspiring::quote());

})->purpose('Display an inspiring quote');

Schedule::command('tracking:import-bookings --limit=1000')->everyThirtyMinutes()->withoutOverlapping();

// Despacha únicamente shipments cuyo intervalo de consulta ya venció.
Schedule::command('tracking:dispatch')->everyTenMinutes()->withoutOverlapping();
