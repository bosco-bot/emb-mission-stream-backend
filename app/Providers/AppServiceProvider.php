<?php

namespace App\Providers;

use App\Models\AdminNotification;
use App\Services\MediaMetadataService;
use HorizonsPlus\CollectorLaravel\Events\DigestReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaMetadataService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(DigestReceived::class, function (DigestReceived $event) {
            $digest = $event->payload['digest'] ?? [];

            AdminNotification::create([
                'type' => 'horizons_digest',
                'headline' => $digest['headline'] ?? 'Nouveau point de maintenance',
                'tone' => $digest['tone'] ?? 'info',
                'payload' => $event->payload,
            ]);
        });
    }
}
