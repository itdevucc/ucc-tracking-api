<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {

    $this->comment(Inspiring::quote());

})->purpose('Display an inspiring quote');

Schedule::command('tracking:import-bookings --limit=1000')->everyThirtyMinutes()->withoutOverlapping();

// Activar después de validar manualmente las credenciales de ambas navieras.
// Schedule::command('tracking:dispatch')->everyTenMinutes()->withoutOverlapping();
