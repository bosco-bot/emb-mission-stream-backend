<?php

namespace App\Providers;

use App\Models\AdminNotification;
use App\Services\MediaMetadataService;
use HorizonsPlus\CollectorLaravel\Events\DigestCancelled;
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
            $digestId = $digest['id'] ?? null;

            if ($digestId) {
                $existing = AdminNotification::query()
                    ->where('type', 'horizons_digest')
                    ->where('digest_id', $digestId)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'headline' => $digest['headline'] ?? $existing->headline,
                        'tone' => $digest['tone'] ?? $existing->tone,
                        'payload' => $event->payload,
                        'cancelled_at' => null,
                    ]);

                    return;
                }
            }

            AdminNotification::create([
                'type' => 'horizons_digest',
                'digest_id' => $digestId,
                'headline' => $digest['headline'] ?? 'Nouveau point de maintenance',
                'tone' => $digest['tone'] ?? 'info',
                'payload' => $event->payload,
            ]);
        });

        Event::listen(DigestCancelled::class, function (DigestCancelled $event) {
            $digestId = $event->payload['digest']['id'] ?? null;

            if (! $digestId) {
                return;
            }

            AdminNotification::query()
                ->where('type', 'horizons_digest')
                ->where('digest_id', $digestId)
                ->whereNull('cancelled_at')
                ->update([
                    'cancelled_at' => $event->payload['cancelled_at'] ?? now(),
                ]);
        });
    }
}
