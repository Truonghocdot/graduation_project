<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('matching:dispatch')
    ->everyFiveSeconds()
    ->withoutOverlapping();

Schedule::command('matching:expire-offers')
    ->everyFiveSeconds()
    ->withoutOverlapping();

Schedule::command('outbox:publish')
    ->everySecond()
    ->withoutOverlapping();
