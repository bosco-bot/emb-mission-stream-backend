<?php

namespace App\Services;

use App\Models\MediaFile;
use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class AntMediaVoDService
{
    private string $antMediaStreamsPath;
    private string $antMediaBaseUrl;

    public function __construct()
    {
        $this->antMediaStreamsPath = '/usr/local/antmedia/webapps/LiveApp/streams';
        $this->antMediaBaseUrl = 'https://tv.embmission.com/webtv-live/streams';
    }

    /**
     * Pré-convertit un MediaFile en HLS (vod_mf_{media_file_id})
     */
    public function createVodForMediaFile(MediaFile $mediaFile): array
    {
        // Verrou par fichier pour éviter les conversions simultanées
        $lockKey = 'vod-conversion-lock:' . $mediaFile->id;
        $lock = Cache::lock($lockKey, 3600); // 1 heure max
        
        if (!$lock->get()) {
            Log::info('⏸️ Conversion déjà en cours, skip', [
                'media_file_id' => $mediaFile->id
            ]);
            return [
                'success' => false,
                'message' => 'Conversion déjà en cours pour ce fichier'
            ];
        }

        try {
            $sourcePath = storage_path('app/media/' . $mediaFile->file_path);
            if (!file_exists($sourcePath)) {
                return [
                    'success' => false,
                    'message' => 'Fichier source introuvable: ' . $sourcePath
                ];
            }

            $vodName = $this->buildVodNameForMediaFile($mediaFile->id);
            $hlsDir = $this->antMediaStreamsPath . '/' . $vodName;
            $playlistPath = $hlsDir . '/playlist.m3u8';

            if (file_exists($playlistPath)) {
                // Si un HLS existe déjà, valider qu'il est complet (ENDLIST + segments suffisants)
                if ($this->isVodComplete($vodName)) {
                    $streamUrl = $this->buildHlsUrlFromVodName($vodName);
                    Log::info('📁 VoD HLS média déjà généré - réutilisation', [
                        'media_file_id' => $mediaFile->id,
                        'playlist' => $playlistPath
                    ]);
                    return [
                        'success' => true,
                        'message' => 'VoD HLS déjà existant',
                        'vod_file_name' => $vodName,
                        'stream_url' => $streamUrl
                    ];
                }
                // Sinon, purge avant régénération
                try {
                    File::deleteDirectory($hlsDir);
                    Log::warning('♻️ VoD HLS incomplet détecté, purge avant régénération', [
                        'media_file_id' => $mediaFile->id,
                        'dir' => $hlsDir,
                    ]);
                } catch (Throwable $e) {
                    Log::warning('⚠️ Échec purge VoD HLS avant régénération: ' . $e->getMessage(), [
                        'media_file_id' => $mediaFile->id,
                    ]);
                }
            }

            Log::info('🔄 Pré-conversion HLS média en cours', [
                'media_file_id' => $mediaFile->id,
                'source' => $sourcePath,
                'target_dir' => $hlsDir
            ]);

            $this->prepareHlsDirectory($hlsDir);
            $conversion = $this->generateHlsFromMp4($mediaFile, $sourcePath, $hlsDir, $playlistPath);
            if (!$conversion['success']) {
                return $conversion;
            }

            // Validation stricte: ENDLIST + au moins 2 segments
            if (!$this->isVodComplete($vodName)) {
                // Une tentative de rattrapage: purge + reconversion
                try {
                    File::deleteDirectory($hlsDir);
                } catch (Throwable $e) {
                    Log::warning('⚠️ Échec purge après conversion incomplète: ' . $e->getMessage(), [
                        'media_file_id' => $mediaFile->id,
                    ]);
                }
                $this->prepareHlsDirectory($hlsDir);
                $conversion = $this->generateHlsFromMp4($mediaFile, $sourcePath, $hlsDir, $playlistPath);
                if (!($conversion['success'] ?? false) || !$this->isVodComplete($vodName)) {
                    return [
                        'success' => false,
                        'message' => 'Conversion HLS incomplète (absence ENDLIST/segments).'
                    ];
                }
            }

            $streamUrl = $this->buildHlsUrlFromVodName($vodName);
            Log::info('✅ VoD HLS média généré', [
                'media_file_id' => $mediaFile->id,
                'stream_url' => $streamUrl
            ]);

            return [
                'success' => true,
                'message' => 'VoD média converti en HLS',
                'vod_file_name' => $vodName,
                'stream_url' => $streamUrl,
                'playlist_path' => $playlistPath
            ];
        } catch (\Exception $e) {
            Log::error('❌ Erreur pré-conversion VoD média: ' . $e->getMessage(), [
                'media_file_id' => $mediaFile->id ?? null
            ]);
            return [
                'success' => false,
                'message' => 'Erreur pré-conversion VoD média: ' . $e->getMessage()
            ];
        } finally {
            // Libérer le verrou dans tous les cas
            try {
                $lock->release();
            } catch (\Exception $e) {
                Log::warning('⚠️ Erreur libération verrou conversion: ' . $e->getMessage());
            }
        }
    }

    public function buildVodNameForMediaFile(int $mediaFileId): string
    {
        return 'vod_mf_' . $mediaFileId;
    }

    /**
     * Crée ou rafraîchit un VoD HLS (playlist + segments TS)
     */
    public function createVoDStream(WebTVPlaylistItem $item): array
    {
        try {
            if (!$item->video_file_id) {
                return [
                    'success' => false,
                    'message' => 'Aucun fichier vidéo associé à cet item'
                ];
            }

            $mediaFile = MediaFile::find($item->video_file_id);
            if (!$mediaFile) {
                return [
                    'success' => false,
                    'message' => 'Fichier média non trouvé'
                ];
            }

            $hlsDirName = 'vod_' . $item->id;
            $hlsDir = $this->antMediaStreamsPath . '/' . $hlsDirName;
            $playlistPath = $hlsDir . '/playlist.m3u8';
            $sourcePath = storage_path('app/media/' . $mediaFile->file_path);

            if (!file_exists($sourcePath)) {
                Log::error('❌ Fichier source introuvable pour la conversion HLS', [
                    'item_id' => $item->id,
                    'source_path' => $sourcePath,
                    'file_path_from_db' => $mediaFile->file_path
                ]);

                return [
                    'success' => false,
                    'message' => 'Fichier source introuvable: ' . $sourcePath
                ];
            }

            if (file_exists($playlistPath)) {
                $streamUrl = $this->buildHlsUrl($hlsDirName);
                Log::info('📁 VoD HLS déjà généré - réutilisation', [
                    'item_id' => $item->id,
                    'playlist' => $playlistPath
                ]);

                $item->update([
                    'ant_media_item_id' => $hlsDirName,
                    'sync_status' => 'synced',
                    'stream_url' => $streamUrl
                ]);

                return [
                    'success' => true,
                    'message' => 'VoD HLS déjà existant',
                    'vod_file_name' => $hlsDirName,
                    'stream_url' => $streamUrl
                ];
            }

            Log::info('🔄 Conversion HLS en cours', [
                'item_id' => $item->id,
                'source' => $sourcePath,
                'target_dir' => $hlsDir
            ]);

            $this->prepareHlsDirectory($hlsDir);
            $conversion = $this->generateHlsFromMp4($mediaFile, $sourcePath, $hlsDir, $playlistPath);

            if (!$conversion['success']) {
                return $conversion;
            }

            $streamUrl = $this->buildHlsUrl($hlsDirName);
            $item->update([
                'ant_media_item_id' => $hlsDirName,
                'sync_status' => 'synced',
                'stream_url' => $streamUrl
            ]);

            Log::info('✅ VoD HLS généré avec succès', [
                'item_id' => $item->id,
                'stream_url' => $streamUrl
            ]);

            return [
                'success' => true,
                'message' => 'VoD converti en HLS avec succès',
                'vod_file_name' => $hlsDirName,
                'stream_url' => $streamUrl,
                'playlist_path' => $playlistPath
            ];

        } catch (\Exception $e) {
            Log::error('❌ Erreur création VoD HLS: ' . $e->getMessage(), [
                'item_id' => $item->id ?? null
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la création du VoD HLS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Liste les VoD HLS disponibles
     */
    public function getVodFiles(): array
    {
        try {
            $directories = collect(glob($this->antMediaStreamsPath . '/vod_*', GLOB_ONLYDIR))
                ->map(function (string $directory) {
                    $name = basename($directory);
                    $playlist = $directory . '/playlist.m3u8';

                    return [
                        'name' => $name,
                        'url' => $this->buildHlsUrl($name),
                        'playlist_exists' => file_exists($playlist),
                        'segment_count' => $this->countSegments($directory),
                        'created_at' => filemtime($directory)
                    ];
                })
                ->sortByDesc('created_at')
                ->values()
                ->toArray();

            Log::info('📋 Liste des VoD HLS récupérée', ['count' => count($directories)]);

            return $directories;
        } catch (\Exception $e) {
            Log::error('❌ Erreur récupération VoD HLS: ' . $e->getMessage());

            return [];
        }
    }

    public function getVodUrl(string $vodName): string
    {
        return $this->buildHlsUrl($vodName);
    }

    public function checkVoD(WebTVPlaylistItem $item): array
    {
        try {
            $vodName = $item->ant_media_item_id;

            if (!$vodName) {
                return [
                    'success' => false,
                    'message' => 'Aucun VoD associé'
                ];
            }

            $vodDir = $this->antMediaStreamsPath . '/' . $vodName;
            $playlist = $vodDir . '/playlist.m3u8';

            if (!is_dir($vodDir) || !file_exists($playlist)) {
                return [
                    'success' => false,
                    'message' => "VoD absent ou playlist manquante"
                ];
            }

            return [
                'success' => true,
                'message' => 'VoD HLS présent',
                'vod_path' => $vodDir,
                'playlist_path' => $playlist,
                'stream_url' => $this->buildHlsUrl($vodName),
                'segment_count' => $this->countSegments($vodDir)
            ];
        } catch (\Exception $e) {
            Log::error('❌ Erreur vérification VoD HLS: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification du VoD HLS: ' . $e->getMessage()
            ];
        }
    }

    public function deleteVoDStream(WebTVPlaylistItem $item): array
    {
        try {
            $vodName = $item->ant_media_item_id;

            if (!$vodName) {
                return [
                    'success' => true,
                    'message' => 'Aucun VoD HLS à supprimer'
                ];
            }

            $otherItems = WebTVPlaylistItem::where('ant_media_item_id', $vodName)
                ->where('id', '!=', $item->id)
                ->exists();

            if ($otherItems) {
                Log::info('📁 VoD HLS utilisé par d’autres items, conservation', [
                    'item_id' => $item->id,
                    'vod_name' => $vodName
                ]);

                return [
                    'success' => true,
                    'message' => 'VoD conservé car utilisé par d’autres items'
                ];
            }

            $vodDir = $this->antMediaStreamsPath . '/' . $vodName;

            if (is_dir($vodDir)) {
                File::deleteDirectory($vodDir);
                Log::info('✅ VoD HLS supprimé', [
                    'item_id' => $item->id,
                    'vod_name' => $vodName
                ]);

                return [
                    'success' => true,
                    'message' => 'VoD HLS supprimé'
                ];
            }

            return [
                'success' => true,
                'message' => 'VoD HLS absent (rien à supprimer)'
            ];
        } catch (\Exception $e) {
            Log::error('❌ Erreur suppression VoD HLS: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du VoD HLS: ' . $e->getMessage()
            ];
        }
    }

    private function prepareHlsDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            File::deleteDirectory($directory);
        }

        File::makeDirectory($directory, 0755, true, true);
    }

    private function generateHlsFromMp4(MediaFile $mediaFile, string $sourcePath, string $targetDir, string $playlistPath): array
    {
        $segmentPattern = $targetDir . '/segment_%05d.ts';

        // Paramètres audio
        $audioSampleRate = $mediaFile->sample_rate ?: 44100;
        $audioSampleRate = max(8000, min(96000, (int) $audioSampleRate));
        
        // Paramètres vidéo adaptatifs
        $frameRate = $mediaFile->frame_rate ?: 25;
        $frameRate = max(15, min(60, (int) $frameRate)); // Sécurité
        
        // GOP de 2 secondes exactement (adapté au framerate)
        $gopSize = $frameRate * 2;
        
        // Bitrate adaptatif selon le fichier source
        $sourceBitrate = $mediaFile->bit_rate ?: 2500000;
        $targetBitrate = min($sourceBitrate * 1.1, 3500000); // Max 10% d'augmentation
        $targetBitrateK = round($targetBitrate / 1000) . 'k';
        $maxrateK = round($targetBitrate * 1.08 / 1000) . 'k';
        $bufsizeK = round($targetBitrate * 2 / 1000) . 'k';
        
        Log::info('🎬 Paramètres VoD calculés', [
            'frame_rate' => $frameRate,
            'gop_size' => $gopSize,
            'target_bitrate' => $targetBitrateK,
            'source_bitrate' => round($sourceBitrate / 1000) . 'k'
        ]);

        $commandParts = [
            'ffmpeg',
            '-y',
            '-i ' . escapeshellarg($sourcePath),
            '-c:v libx264',
            '-preset medium', // Meilleur équilibre qualité/vitesse
            '-profile:v high',
            '-level 4.1',
            '-pix_fmt yuv420p',
            // Forcer un framerate constant pour éviter les segments HLS trop courts
            '-r 25',
            '-vsync cfr',
            '-b:v ' . $targetBitrateK,
            '-maxrate ' . $maxrateK,
            '-bufsize ' . $bufsizeK,
            '-g ' . $gopSize, // GOP adaptatif 2s
            '-keyint_min ' . $gopSize,
            '-sc_threshold 0',
            '-force_key_frames "expr:gte(t,n_forced*2)"',
            '-movflags +faststart',
            '-c:a aac',
            '-b:a 192k',
            '-ac 2',
            '-ar ' . $audioSampleRate,
            '-hls_time 2',
            '-hls_playlist_type vod',
            '-hls_flags independent_segments',
            '-hls_segment_filename ' . escapeshellarg($segmentPattern),
            escapeshellarg($playlistPath),
        ];
        $ffmpegCmd = implode(' ', $commandParts) . ' 2>&1';

        $output = [];
        $returnVar = 0;
        exec($ffmpegCmd, $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error('❌ Erreur conversion HLS', [
                'command' => $ffmpegCmd,
                'output' => implode("\n", $output)
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la conversion HLS'
            ];
        }

        return ['success' => true];
    }

    private function countSegments(string $directory): int
    {
        return count(glob(rtrim($directory, '/') . '/*.ts'));
    }

    private function buildHlsUrl(string $hlsDirName): string
    {
        return rtrim($this->antMediaBaseUrl, '/') . '/' . $hlsDirName . '/playlist.m3u8';
    }

    public function buildHlsUrlFromVodName(string $vodName): string
    {
        return $this->buildHlsUrl($vodName);
    }

    /**
     * Vérifie si le HLS d'un vod_mf est complet (ENDLIST présent et >= 2 segments).
     */
    public function isVodComplete(string $vodName): bool
    {
        $base = rtrim($this->antMediaStreamsPath, '/').'/'.$vodName;
        $playlist = $base.'/playlist.m3u8';
        if (!is_file($playlist)) {
            return false;
        }
        $content = @file_get_contents($playlist);
        if ($content === false) {
            return false;
        }
        if (strpos($content, '#EXT-X-ENDLIST') === false) {
            return false;
        }
        $segments = glob($base.'/segment_*.ts') ?: [];
        return count($segments) >= 2;
    }
}
