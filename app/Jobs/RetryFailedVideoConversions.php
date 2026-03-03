<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\AntMediaVoDService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RetryFailedVideoConversions implements ShouldQueue
{
    use Queueable;

    public $tries = 1; // Une seule tentative par exécution (le scheduler relancera)
    public $timeout = 300; // 5 minutes max pour le scan (les conversions sont dans d'autres jobs)

    /**
     * Execute the job.
     * 
     * Scanne les fichiers vidéo et dispatche des jobs individuels pour les conversions nécessaires
     */
    public function handle(): void
    {
        Log::info('🔄 Début scan des conversions HLS échouées/incomplètes');

        $vodService = new AntMediaVoDService();
        $streamsBase = '/usr/local/antmedia/webapps/LiveApp/streams';
        
        // Récupérer les fichiers vidéo uploadés récemment (dernières 48h)
        // Limiter aux fichiers récents pour éviter de scanner toute la base à chaque fois
        $videoFiles = MediaFile::where('file_type', 'video')
            ->whereNotNull('file_path')
            ->where('created_at', '>=', now()->subHours(48))
            ->orderBy('created_at', 'desc')
            ->get();

        $checked = 0;
        $dispatched = 0;
        $skipped = 0;
        $errors = 0;
        $maxJobsPerCycle = 10; // Limiter le nombre de jobs dispatchés par cycle

        foreach ($videoFiles as $mediaFile) {
            // Limiter le nombre de jobs dispatchés pour éviter la surcharge
            if ($dispatched >= $maxJobsPerCycle) {
                Log::info('⏸️ Limite de jobs atteinte pour ce cycle', [
                    'max_jobs' => $maxJobsPerCycle,
                    'dispatched' => $dispatched
                ]);
                break;
            }

            $checked++;
            
            try {
                $vodName = $vodService->buildVodNameForMediaFile($mediaFile->id);
                $vodDir = $streamsBase . '/' . $vodName;
                $playlistPath = $vodDir . '/playlist.m3u8';

                // Vérifier si le fichier source existe toujours
                $sourcePath = storage_path('app/media/' . $mediaFile->file_path);
                if (!file_exists($sourcePath)) {
                    Log::warning('⚠️ Fichier source introuvable, skip', [
                        'media_file_id' => $mediaFile->id,
                        'source_path' => $sourcePath
                    ]);
                    $skipped++;
                    continue;
                }

                // Cas 1: HLS n'existe pas du tout
                if (!file_exists($playlistPath)) {
                    Log::info('🔄 HLS absent, dispatch job conversion', [
                        'media_file_id' => $mediaFile->id,
                        'vod_name' => $vodName
                    ]);
                    
                    ConvertVideoToHls::dispatch($mediaFile->id);
                    $dispatched++;
                    continue;
                }

                // Cas 2: HLS existe mais est incomplet (pas d'ENDLIST ou segments manquants)
                if (!$vodService->isVodComplete($vodName)) {
                    Log::info('🔄 HLS incomplet détecté, dispatch job conversion', [
                        'media_file_id' => $mediaFile->id,
                        'vod_name' => $vodName,
                        'vod_dir' => $vodDir
                    ]);

                    ConvertVideoToHls::dispatch($mediaFile->id);
                    $dispatched++;
                    continue;
                }

                // Cas 3: HLS existe et est complet - rien à faire
                $skipped++;

            } catch (\Exception $e) {
                $errors++;
                Log::error('❌ Erreur lors du traitement du fichier vidéo', [
                    'media_file_id' => $mediaFile->id ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info('✅ Scan terminé', [
            'checked' => $checked,
            'dispatched' => $dispatched,
            'skipped' => $skipped,
            'errors' => $errors,
            'max_jobs_per_cycle' => $maxJobsPerCycle
        ]);
    }
}
