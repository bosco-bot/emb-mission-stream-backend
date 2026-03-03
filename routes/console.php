<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('unified-stream:remux-pending --limit=5')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Surveillance de la synchronisation WebTV
Schedule::command('webtv:check-sync --fix --alert')
    ->hourly()
    ->withoutOverlapping();

// Lier automatiquement les items pending aux HLS pré-convertis (toutes les 2 minutes)
Schedule::command('webtv:link-preconverted --limit=100')
    ->everyTwoMinutes()
    ->withoutOverlapping();

// Relancer les conversions HLS échouées ou incomplètes (toutes les 15 minutes)
// Limité aux fichiers des dernières 48h pour optimiser les performances
Schedule::job(new \App\Jobs\RetryFailedVideoConversions())
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('retry-failed-video-conversions');

// Mise à jour des statistiques WebTV et WebRadio (toutes les heures)
Schedule::command('stats:update')
    ->hourly()
    ->withoutOverlapping();
