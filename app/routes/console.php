<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('problems:check-deadlines')->daily();
Schedule::command('archive:process')->daily();
Schedule::command('audit:purge')->daily();
Schedule::command('reporters:inactive-deletion')->daily();
