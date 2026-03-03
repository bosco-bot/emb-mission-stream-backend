<?php

namespace App\Services;

use App\Models\WebTVPlaylistItem;
use Illuminate\Support\Facades\Log;

class FFmpegHLSService
{
    private string $hlsOutputPath;
    private string $playlistFilePath;
    private string $hlsBaseUrl;

    public function __construct()
    {
        $this->hlsOutputPath = '/var/www/emb-mission/storage/app/public/';
        $this->playlistFilePath = '/tmp/webtv_playlist.txt';
        $this->hlsBaseUrl = 'https://tv.embmission.com/storage/';
    }

    /**
     * Créer le stream HLS continu à partir des symlinks
     */
    public function createContinuousStream(): array
    {
        try {
            // 1. Récupérer les items synchronisés
            $items = WebTVPlaylistItem::where('sync_status', 'synced')
                ->orderBy('order')
                ->get();

            if ($items->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'Aucun item synchronisé trouvé'
                ];
            }

            // 2. Créer le fichier playlist pour FFmpeg
            $this->createFFmpegPlaylist($items);

            // 3. Arrêter le processus FFmpeg existant s'il existe
            $this->stopExistingStream();

            // 4. Démarrer le nouveau stream HLS
            $result = $this->startHLSStream();

            if ($result['success']) {
                Log::info("✅ Stream HLS continu créé", [
                    'items_count' => $items->count(),
                    'hls_url' => $this->hlsBaseUrl . 'continuous.m3u8'
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("❌ Erreur création stream HLS: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erreur lors de la création du stream HLS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Créer le fichier playlist pour FFmpeg
     */
    private function createFFmpegPlaylist($items): void
    {
        $playlistContent = '';

        foreach ($items as $item) {
            $vodPath = "/usr/local/antmedia/webapps/LiveApp/streams/vod_{$item->id}.mp4";

            if (file_exists($vodPath)) {
                $playlistContent .= "file '{$vodPath}'\n";
            }
        }

        file_put_contents($this->playlistFilePath, $playlistContent);

        Log::info("📝 Playlist FFmpeg créée", [
            'file' => $this->playlistFilePath,
            'items_count' => substr_count($playlistContent, 'file ')
        ]);
    }

    /**
     * Démarrer le stream HLS avec FFmpeg
     */
    private function startHLSStream(): array
    {
        try {
            // Créer le dossier de sortie s'il n'existe pas
            if (!is_dir($this->hlsOutputPath) && !mkdir($this->hlsOutputPath, 0755, true)) {
                throw new \Exception("Impossible de créer le dossier HLS : {$this->hlsOutputPath}");
            }

            // Nettoyer le log précédent
            file_put_contents('/tmp/ffmpeg_hls.log', '');

            // Commande FFmpeg sécurisée et stable
            $command = sprintf(
                'nohup ffmpeg -y -f concat -safe 0 -stream_loop -1 -i %s ' .
                '-c:v copy ' .
                '-c:a copy ' .
                '-f hls -hls_time 4 -hls_list_size 5 ' .
                '-hls_flags delete_segments+append_list+omit_endlist ' .
                '-hls_segment_type mpegts ' .
                '-hls_segment_filename %scontinuous_%%03d.ts ' .
                '%scontinuous.m3u8 > /tmp/ffmpeg_hls.log 2>&1 & echo $!',
                escapeshellarg($this->playlistFilePath),
                escapeshellarg($this->hlsOutputPath),
                escapeshellarg($this->hlsOutputPath)
            );

            // Exécuter la commande et récupérer le PID
            $pid = shell_exec($command);
            $pid = trim($pid);

            if ($pid && is_numeric($pid)) {
                file_put_contents('/tmp/ffmpeg_hls.pid', $pid);

                // Attente dynamique de la création du fichier M3U8
                $m3u8Path = $this->hlsOutputPath . 'continuous.m3u8';
                $wait = 0;
                while ($wait < 10 && !file_exists($m3u8Path)) {
                    sleep(1);
                    $wait++;
                }

                if (file_exists($m3u8Path)) {
                    return [
                        'success' => true,
                        'message' => 'Stream HLS démarré avec succès',
                        'hls_url' => $this->hlsBaseUrl . 'continuous.m3u8',
                        'pid' => $pid
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Fichier M3U8 non créé après 10 secondes'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Impossible de démarrer FFmpeg'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur FFmpeg: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Arrêter le stream HLS existant
     */
    private function stopExistingStream(): void
    {
        $pidFile = '/tmp/ffmpeg_hls.pid';

        if (file_exists($pidFile)) {
            $pid = (int) trim(file_get_contents($pidFile));

            if ($pid > 0 && posix_getpgid($pid)) {
                shell_exec("kill -9 $pid 2>/dev/null");
                unlink($pidFile);
                Log::info("🛑 Stream HLS existant arrêté", ['pid' => $pid]);
            }
        }

        // Nettoyer les anciens fichiers HLS
        $this->cleanupHLSFiles();
    }

    /**
     * Nettoyer les fichiers HLS existants
     */
    private function cleanupHLSFiles(): void
    {
        $files = glob($this->hlsOutputPath . 'continuous*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Obtenir l'URL du stream HLS
     */
    public function getHLSStreamUrl(): string
    {
        return $this->hlsBaseUrl . 'continuous.m3u8';
    }

    /**
     * Vérifier si le stream HLS est actif
     */
    public function isStreamActive(): bool
    {
        $m3u8Path = $this->hlsOutputPath . 'continuous.m3u8';
        $pidFile = '/tmp/ffmpeg_hls.pid';

        if (!file_exists($m3u8Path) || !file_exists($pidFile)) {
            return false;
        }

        $pid = (int) trim(file_get_contents($pidFile));
        return $pid > 0 && posix_getpgid($pid);
    }
}
