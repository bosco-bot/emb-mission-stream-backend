<?php

namespace HorizonsPlus\CollectorLaravel;

use HorizonsPlus\CollectorLaravel\Http\Controllers\DigestWebhookController;
use HorizonsPlus\CollectorLaravel\Http\Middleware\VerifyDigestWebhookSignature;
use HorizonsPlus\CollectorLaravel\Http\Middleware\VerifyMonitoringToken;
use HorizonsPlus\CollectorLaravel\Services\HealthCollector;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HorizonsCollectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/horizons-collector.php', 'horizons-collector');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/horizons-collector.php' => config_path('horizons-collector.php'),
        ], 'horizons-collector-config');
        
        
        Route::middleware(['api', VerifyMonitoringToken::class])
            ->group(function () {
                Route::get('/api/monitoring/health', function () {
                    return response()->json(app(HealthCollector::class)->collect());
                })->name('horizons.monitoring.health');
            });
        
            Route::middleware(['api', VerifyDigestWebhookSignature::class])
            ->group(function () {
                Route::post('/api/horizons/digests', [DigestWebhookController::class, '__invoke'])
                    ->name('horizons.digests.webhook');
            }); 
    }
}
