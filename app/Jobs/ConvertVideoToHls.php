<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\AntMediaVoDService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ConvertVideoToHls implements ShouldQueue
{
    use Queueable;

    public $tries = 2; // 2 tentatives max (1 retry automatique)
    public $timeout = 3600; // 1 heure max par conversion
    public $backoff = 300; // Attendre 5 minutes avant retry

    private int $mediaFileId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $mediaFileId)
    {
        $this->mediaFileId = $mediaFileId;
    }

    /**
     * Execute the job.
     * 
     * Convertit un seul fichier vidéo en HLS
     */
    public function handle(): void
    {
        $mediaFile = MediaFile::find($this->mediaFileId);
        
        if (!$mediaFile) {
            Log::warning('⚠️ MediaFile introuvable, skip conversion', [
                'media_file_id' => $this->mediaFileId
            ]);
            return;
        }

        if ($mediaFile->file_type !== 'video') {
            Log::warning('⚠️ Fichier n\'est pas une vidéo, skip', [
                'media_file_id' => $this->mediaFileId,
                'file_type' => $mediaFile->file_type
            ]);
            return;
        }

        $vodService = new AntMediaVoDService();
        $vodName = $vodService->buildVodNameForMediaFile($mediaFile->id);
        $streamsBase = '/usr/local/antmedia/webapps/LiveApp/streams';
        $vodDir = $streamsBase . '/' . $vodName;
        $playlistPath = $vodDir . '/playlist.m3u8';

        // Vérifier si le fichier source existe
        $sourcePath = storage_path('app/media/' . $mediaFile->file_path);
        if (!file_exists($sourcePath)) {
            Log::warning('⚠️ Fichier source introuvable, skip conversion', [
                'media_file_id' => $this->mediaFileId,
                'source_path' => $sourcePath
            ]);
            return;
        }

        // Cas 1: HLS existe déjà et est complet - rien à faire
        if (file_exists($playlistPath) && $vodService->isVodComplete($vodName)) {
            Log::info('✅ HLS déjà complet, skip conversion', [
                'media_file_id' => $this->mediaFileId,
                'vod_name' => $vodName
            ]);
            return;
        }

        // Cas 2: HLS existe mais est incomplet - nettoyer d'abord
        if (file_exists($playlistPath) && !$vodService->isVodComplete($vodName)) {
            Log::info('🧹 HLS incomplet détecté, nettoyage avant conversion', [
                'media_file_id' => $this->mediaFileId,
                'vod_name' => $vodName
            ]);
            
            try {
                if (File::isDirectory($vodDir)) {
                    File::deleteDirectory($vodDir);
                    Log::info('✅ Dossier HLS incomplet supprimé', [
                        'media_file_id' => $this->mediaFileId
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ Erreur nettoyage dossier HLS', [
                    'media_file_id' => $this->mediaFileId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Cas 3: HLS n'existe pas ou a été nettoyé - lancer la conversion
        Log::info('🔄 Début conversion HLS', [
            'media_file_id' => $this->mediaFileId,
            'vod_name' => $vodName
        ]);

        $result = $vodService->createVodForMediaFile($mediaFile);
        
        if ($result['success'] ?? false) {
            Log::info('✅ Conversion HLS réussie', [
                'media_file_id' => $this->mediaFileId,
                'vod_name' => $vodName,
                'stream_url' => $result['stream_url'] ?? null
            ]);
        } else {
            Log::error('❌ Conversion HLS échouée', [
                'media_file_id' => $this->mediaFileId,
                'message' => $result['message'] ?? 'Unknown error'
            ]);
            // Relancer l'exception pour déclencher le retry automatique
            throw new \Exception('Conversion HLS échouée: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Job ConvertVideoToHls échoué définitivement', [
            'media_file_id' => $this->mediaFileId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
