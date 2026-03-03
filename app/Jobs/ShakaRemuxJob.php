<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ShakaRemuxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800; // 30 minutes pour laisser le temps au packaging

    private string $vodId;

    public function __construct(string $vodId)
    {
        $this->vodId = $vodId;
    }

    public function handle(): void
    {
        $workspace = '/tmp/remux_' . $this->vodId . '_' . uniqid();

        Log::info('🚀 Lancement remux Shaka', [
            'vod' => $this->vodId,
            'workspace' => $workspace,
        ]);

        $exitCode = Artisan::call('vod:remux', [
            'vodId' => $this->vodId,
            '--use-master' => true,
            '--use-shaka' => true,
            '--workspace' => $workspace,
        ]);

        if ($exitCode !== 0) {
            $output = Artisan::output();
            Log::error('❌ Remux Shaka échoué', [
                'vod' => $this->vodId,
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            throw new RuntimeException("Remux Shaka échoué pour {$this->vodId} (code {$exitCode})");
        }

        Log::info('✅ Remux Shaka terminé', [
            'vod' => $this->vodId,
        ]);
    }
}


















