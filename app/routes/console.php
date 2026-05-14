<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('problems:check-deadlines')->daily();
Schedule::command('archive:process')
    ->dailyAt('00:10')
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping();
Schedule::command('audit:purge')
    ->dailyAt('00:20')
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping();
Schedule::command('reporters:inactive-deletion')
    ->dailyAt('00:00')
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping();
Schedule::command('problems:hide-resolved-privacy')
    ->dailyAt('00:05')
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping();
Schedule::command('gis:pull-attachments')
    ->everyFifteenMinutes()
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping();
Schedule::command('dvs:pull-statuses')
    ->daily()
    ->timezone('Europe/Vilnius')
    ->withoutOverlapping();
