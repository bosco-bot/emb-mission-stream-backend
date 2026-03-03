<?php

namespace App\Console\Commands;

use App\Services\UnifiedHlsBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunUnifiedStreamTest extends Command
{
    protected $signature = 'unified-stream:test {--once : Exécuter une seule itération} {--sleep=2 : Intervalle en secondes entre les itérations}';

    protected $description = 'Construit en continu la playlist HLS unifiée (Mode Test) pour EMB Mission TV.';

    private UnifiedHlsBuilder $builder;

    public function __construct(UnifiedHlsBuilder $builder)
    {
        parent::__construct();
        $this->builder = $builder;
    }

    public function handle(): int
    {
        // ✅ ACTIVER LE MODE TEST
        $this->builder->setTestMode(true);
        $this->info('🧪 Démarrage du générateur de flux unifié en MODE TEST (unified_test.m3u8)');

        $once = (bool) $this->option('once');
        $sleep = (int) $this->option('sleep');
        if ($sleep < 1) {
            $sleep = 1;
        }

        $iteration = 0;

        do {
            $iteration++;
            $start = microtime(true);

            try {
                $success = $this->builder->build();
                $durationMs = (int) round((microtime(true) - $start) * 1000);

                if ($success) {
                    $message = sprintf('Itération %d : playlist TEST générée en %d ms', $iteration, $durationMs);
                    $this->info($message);
                    Log::debug('✅ unified-stream:test', ['iteration' => $iteration, 'duration_ms' => $durationMs]);
                } else {
                    $message = sprintf('Itération %d : échec génération playlist TEST', $iteration);
                    $this->warn($message);
                    Log::warning('⚠️ unified-stream:test', ['iteration' => $iteration]);
                }
            } catch (Throwable $e) {
                $this->error('Erreur lors de la génération du flux unifié TEST : ' . $e->getMessage());
                Log::error('❌ unified-stream:test', [
                    'iteration' => $iteration,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            if ($once) {
                break;
            }

            sleep($sleep);
        } while (true);

        return self::SUCCESS;
    }
}
