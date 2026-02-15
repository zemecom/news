<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    echo Inspiring::quote();
})->purpose('Display an inspiring quote');

Schedule::command('news:crawl')
    ->everyMinute()
    ->withoutOverlapping();
