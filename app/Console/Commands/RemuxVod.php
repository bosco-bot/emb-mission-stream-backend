<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RemuxVod extends Command
{
    protected $signature = 'vod:remux
        {vodId? : Identifiant du dossier VOD (ex: vod_143)}
        {--all : Remuxer tous les VOD disponibles}
        {--dry-run : Simuler les actions sans modifier les fichiers}
        {--ffmpeg-bin=ffmpeg : Chemin vers le binaire ffmpeg}
        {--ffprobe-bin=ffprobe : Chemin vers le binaire ffprobe}
        {--preset=veryfast : Preset x264 à utiliser}
        {--video-bitrate=3500k : Débit vidéo cible}
        {--audio-bitrate=160k : Débit audio cible}
        {--hls-time=2 : Durée des segments générés}
        {--workspace= : Répertoire de travail temporaire}
        {--keep-backup : Conserver les sauvegardes après succès}
        {--force : Ignorer certains contrôles de sécurité (à utiliser avec prudence)}
        {--use-master : Utiliser le fichier maître référencé en base de données plutôt que la playlist Ant Media}
        {--use-shaka : Utiliser Shaka Packager pour générer les playlists HLS}
        {--packager-bin=packager : Binaire Shaka Packager à utiliser}';

    protected $description = 'Remuxer un ou plusieurs VOD Ant Media avec des paramètres HLS homogènes.';

    private string $antMediaStreamsPath = '/usr/local/antmedia/webapps/LiveApp/streams';

    private array $errors = [];

    public function handle(): int
    {
        $vodId = $this->argument('vodId');
        $runAll = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');

        if (!$runAll && !$vodId) {
            $this->error('Veuillez préciser un {vodId} ou utiliser --all.');
            return self::INVALID;
        }

        if ($runAll && $vodId) {
            $this->error('Merci de choisir soit {vodId}, soit --all, mais pas les deux.');
            return self::INVALID;
        }

        $targets = $runAll ? $this->discoverVodDirectories() : $this->resolveSingleVod($vodId);

        if (empty($targets)) {
            $this->warn('Aucun dossier VOD à traiter.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Remux de %d VOD%s %s', count($targets), count($targets) > 1 ? 's' : '', $dryRun ? '(simulation)' : ''));

        foreach ($targets as $path) {
            $this->remuxVodDirectory($path);
        }

        if (!empty($this->errors)) {
            $this->error('Certaines opérations ont échoué :');
            foreach ($this->errors as $error) {
                $this->line('- ' . $error);
            }

            return self::FAILURE;
        }

        $this->info('Remux terminé avec succès.');
        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function discoverVodDirectories(): array
    {
        if (!is_dir($this->antMediaStreamsPath)) {
            $this->error('Répertoire Ant Media introuvable : ' . $this->antMediaStreamsPath);
            return [];
        }

        $directories = glob($this->antMediaStreamsPath . '/vod_*', GLOB_ONLYDIR) ?: [];

        return array_values(array_filter($directories, static fn (string $path) => is_dir($path)));
    }

    /**
     * @return array<int, string>
     */
    private function resolveSingleVod(string $vodId): array
    {
        $vodId = trim($vodId);

        if (!Str::startsWith($vodId, 'vod_')) {
            $vodId = 'vod_' . $vodId;
        }

        $path = $this->antMediaStreamsPath . '/' . $vodId;

        if (!is_dir($path)) {
            $this->error(sprintf('Le dossier VOD "%s" est introuvable dans %s', $vodId, $this->antMediaStreamsPath));
            return [];
        }

        return [$path];
    }

    private function remuxVodDirectory(string $vodPath): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $vodId = basename($vodPath);
        $this->line(str_repeat('-', 80));
        $this->info(sprintf('Traitement du VOD %s', $vodId));

        $input = $this->resolveInputSource($vodId, $vodPath);
        if ($input === null) {
            return;
        }

        $workspace = $this->resolveWorkspace($vodId);
        $outputDir = $workspace . '/output';

        $useShaka = (bool) $this->option('use-shaka');

        if ($dryRun) {
            $this->comment(sprintf('[Dry-run] %s traitera "%s" vers "%s"', $useShaka ? 'Shaka Packager' : 'ffmpeg', $input['source'], $outputDir));
        } else {
            $this->prepareWorkspace($workspace, $outputDir);

            try {
                if ($useShaka) {
                    $this->runShakaRemux($input, $outputDir);
                } else {
                    $this->runFfmpegRemux($input, $outputDir);
                }
            } catch (ProcessFailedException $e) {
                $this->recordError($vodId, sprintf('Échec %s : %s', $useShaka ? 'Shaka Packager' : 'FFmpeg', $e->getMessage()));
                $this->cleanupWorkspace($workspace);
                return;
            }
        }

        $backupPath = $vodPath . '_backup_' . Carbon::now()->format('Ymd_His');
        $keepBackup = (bool) $this->option('keep-backup');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->comment(sprintf('[Dry-run] Le dossier original serait sauvegardé dans "%s"', $backupPath));
            $this->comment(sprintf('[Dry-run] Le dossier remuxé remplacerait "%s"', $vodPath));
            $this->cleanupWorkspace($workspace, true);
            return;
        }

        if (File::exists($backupPath)) {
            if ($force) {
                File::deleteDirectory($backupPath);
            } else {
                $this->recordError($vodId, sprintf('Backup déjà présent (%s). Relancez avec --force pour écraser.', $backupPath));
                $this->cleanupWorkspace($workspace);
                return;
            }
        }

        if (!File::move($vodPath, $backupPath)) {
            $this->recordError($vodId, sprintf('Impossible de déplacer le dossier original vers "%s".', $backupPath));
            $this->cleanupWorkspace($workspace);
            return;
        }

        if (!File::move($outputDir, $vodPath)) {
            $this->recordError($vodId, sprintf('Impossible de déployer le dossier remuxé vers "%s".', $vodPath));
            // tentative de restauration
            File::move($backupPath, $vodPath);
            $this->cleanupWorkspace($workspace);
            return;
        }

        $this->comment(sprintf('VOD %s remuxé avec succès.', $vodId));

        if (!$keepBackup) {
            File::deleteDirectory($backupPath);
        }

        $this->cleanupWorkspace($workspace);
    }

    private function resolveWorkspace(string $vodId): string
    {
        $userWorkspace = $this->option('workspace');

        if ($userWorkspace) {
            return rtrim($userWorkspace, '/ ') . '/' . $vodId . '_' . uniqid();
        }

        return storage_path('app/remux/' . $vodId . '_' . uniqid());
    }

    private function prepareWorkspace(string $workspace, string $outputDir): void
    {
        if (!File::exists($workspace)) {
            File::makeDirectory($workspace, 0775, true);
        }

        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0775, true);
        }
    }

    private function cleanupWorkspace(string $workspace, bool $dryRun = false): void
    {
        if ($dryRun) {
            $this->comment(sprintf('[Dry-run] Nettoyage ignoré pour %s', $workspace));
            return;
        }

        if (File::isDirectory($workspace)) {
            File::deleteDirectory($workspace);
        }
    }

    private function runFfmpegRemux(array $input, string $outputDir): void
    {
        $ffmpegBin = (string) $this->option('ffmpeg-bin');

        $hlsTime = (int) $this->option('hls-time');
        if ($hlsTime < 2) {
            $hlsTime = 2;
        }

        $args = [
            $ffmpegBin,
            '-hide_banner',
            '-y',
            '-fflags',
            '+genpts',
        ];

        if ($input['type'] === 'playlist') {
            $args[] = '-protocol_whitelist';
            $args[] = 'file,http,https,tcp,tls,crypto';
        }

        // Paramètres vidéo adaptatifs optimisés
        $frameRate = $this->detectFrameRate($input['source']);
        $sourceBitrate = $this->detectBitrate($input['source']);
        $gopSize = $frameRate * 2; // GOP de 2 secondes exactement
        
        // Bitrate adaptatif selon le fichier source
        $targetBitrate = $this->calculateAdaptiveBitrate($sourceBitrate, (string) $this->option('video-bitrate'));
        
        $this->info(sprintf('🎬 Paramètres calculés: %dfps, GOP=%d, bitrate=%s', $frameRate, $gopSize, $targetBitrate));

        $args = array_merge($args, [
            '-i', $input['source'],
            '-map', '0:v:0?',
            '-map', '0:a:0?',
            '-c:v', 'libx264',
            '-preset', (string) $this->option('preset'),
            '-profile:v', 'high',
            '-level:v', '4.1',
            '-pix_fmt', 'yuv420p',
            '-b:v', $targetBitrate,
            '-maxrate', $this->applyRateMultiplier($targetBitrate, 1.08),
            '-bufsize', $this->applyRateMultiplier($targetBitrate, 2.0),
            '-g', (string) $gopSize,
            '-keyint_min', (string) $gopSize,
            '-sc_threshold', '0',
            '-vsync', 'cfr',
            '-force_key_frames', sprintf('expr:gte(t,n_forced*%d)', $hlsTime),
            '-max_muxing_queue_size', '1024',
            '-avoid_negative_ts', 'make_zero',
            '-start_at_zero',
            '-reset_timestamps', '1',
            '-muxdelay', '0',
            '-muxpreload', '0',
            '-c:a', 'aac',
            '-b:a', (string) $this->option('audio-bitrate'),
            '-ac', '2',
            '-ar', '48000',
            '-mpegts_flags', 'resend_headers+initial_discontinuity',
            '-f', 'hls',
            '-hls_time', (string) $hlsTime,
            '-hls_playlist_type', 'vod',
            '-hls_flags', 'independent_segments',
            '-hls_segment_type', 'mpegts',
            '-hls_segment_filename', $outputDir . '/segment_%05d.ts',
            $outputDir . '/playlist.m3u8',
        ]);

        $process = new Process($args);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->adjustPlaylistDurations($outputDir);
    }

    private function runShakaRemux(array $input, string $outputDir): void
    {
        $packagerBin = (string) $this->option('packager-bin');

        $source = $input['source'] ?? null;

        if (!$source) {
            throw new RuntimeException('Source vidéo introuvable pour Shaka Packager');
        }

        $normalizedSource = $this->prepareShakaSourceForPackager($input, $outputDir);
        $hasAudio = $this->hasAudioStream($normalizedSource);

        $videoInit = $outputDir . '/video_init.mp4';
        $audioInit = $outputDir . '/audio_init.mp4';
        $videoTemplate = $outputDir . '/video_$Number$.m4s';
        $audioTemplate = $outputDir . '/audio_$Number$.m4s';
        $masterPlaylist = $outputDir . '/master.m3u8';

        $args = [
            $packagerBin,
            sprintf('in=%s,stream=video,init_segment=%s,segment_template=%s,playlist_name=video.m3u8', $normalizedSource, $videoInit, $videoTemplate),
        ];

        if ($hasAudio) {
            $args[] = sprintf('in=%s,stream=audio,init_segment=%s,segment_template=%s,playlist_name=audio.m3u8', $normalizedSource, $audioInit, $audioTemplate);
        } else {
            $this->warn('⚠️ Aucun flux audio détecté sur la source normalisée – génération uniquement vidéo.');
        }

        $args = array_merge($args, [
            '--hls_master_playlist_output=' . $masterPlaylist,
            '--hls_playlist_type=VOD',
            '--segment_duration=' . (int) $this->option('hls-time'),
            '--fragment_duration=' . (int) $this->option('hls-time'),
            '--generate_static_live_mpd=false',
        ]);

        $process = new Process($args);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    private function prepareShakaSourceForPackager(array $input, string $outputDir): string
    {
        $ffmpegBin = (string) $this->option('ffmpeg-bin');
        $workspaceDir = dirname($outputDir);
        $normalizedPath = $workspaceDir . '/normalized_source.mp4';

        if (File::exists($normalizedPath)) {
            File::delete($normalizedPath);
        }

        $args = [
            $ffmpegBin,
            '-hide_banner',
            '-y',
            '-fflags',
            '+genpts',
        ];

        if (($input['type'] ?? null) === 'playlist') {
            $args[] = '-protocol_whitelist';
            $args[] = 'file,http,https,tcp,tls,crypto';
        }

        $args = array_merge($args, [
            '-i',
            $input['source'],
            '-map',
            '0:v:0?',
            '-map',
            '0:a:0?',
            '-c:v',
            'copy',
            '-c:a',
            'aac',
            '-b:a',
            (string) $this->option('audio-bitrate'),
            '-ac',
            '2',
            '-ar',
            '48000',
            '-movflags',
            '+faststart',
            $normalizedPath,
        ]);

        $process = new Process($args);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $normalizedPath;
    }

    private function hasAudioStream(string $filePath): bool
    {
        $ffprobeBin = (string) $this->option('ffprobe-bin');

        $process = new Process([
            $ffprobeBin,
            '-v',
            'error',
            '-select_streams',
            'a',
            '-show_entries',
            'stream=index',
            '-of',
            'csv=p=0',
            $filePath,
        ]);

        $process->setTimeout(30);
        $process->run();

        return trim($process->getOutput()) !== '';
    }

    private function resolveInputSource(string $vodId, string $vodPath): ?array
    {
        $useMaster = (bool) $this->option('use-master');

        if ($useMaster) {
            $master = $this->findMasterForVod($vodId);
            if ($master) {
                return [
                    'type' => 'master',
                    'source' => $master,
                ];
            }

            $this->warn(sprintf('Aucun master trouvé pour %s, repli sur la playlist Ant Media.', $vodId));
        }

        $playlistPath = $this->locatePlaylist($vodPath);
        if (!$playlistPath) {
            $this->recordError($vodId, 'Playlist HLS introuvable (playlist.m3u8 / index.m3u8).');
            return null;
        }

        return [
            'type' => 'playlist',
            'source' => $playlistPath,
        ];
    }

    private function findMasterForVod(string $vodId): ?string
    {
        $item = DB::table('webtv_playlist_items')
            ->where('ant_media_item_id', $vodId)
            ->first(['video_file_id']);

        if (!$item || !$item->video_file_id) {
            return null;
        }

        $media = DB::table('media_files')
            ->where('id', $item->video_file_id)
            ->first(['file_path']);

        if (!$media || empty($media->file_path)) {
            return null;
        }

        $fullPath = storage_path('app/media/' . ltrim($media->file_path, '/'));

        if (!is_file($fullPath)) {
            return null;
        }

        return $fullPath;
    }

    private function locatePlaylist(string $vodPath): ?string
    {
        $candidates = [
            $vodPath . '/playlist.m3u8',
            $vodPath . '/index.m3u8',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function applyRateMultiplier(string $rate, float $multiplier): string
    {
        if (!Str::endsWith($rate, 'k')) {
            return $rate;
        }

        $value = (float) Str::beforeLast($rate, 'k');

        if ($value <= 0) {
            return $rate;
        }

        return (string) round($value * $multiplier) . 'k';
    }

    private function recordError(string $vodId, string $message): void
    {
        $this->error(sprintf('[%s] %s', $vodId, $message));
        $this->errors[] = sprintf('%s : %s', $vodId, $message);
    }

    private function adjustPlaylistDurations(string $outputDir): void
    {
        $playlistPath = $outputDir . '/playlist.m3u8';

        if (!is_file($playlistPath)) {
            return;
        }

        $lines = @file($playlistPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        $ffprobeBin = (string) $this->option('ffprobe-bin');
        $updated = false;
        $maxDuration = 0.0;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];
            if (!Str::startsWith($line, '#EXTINF:')) {
                continue;
            }

            $segmentIndex = $i + 1;
            if (!isset($lines[$segmentIndex])) {
                continue;
            }

            $segmentLine = trim($lines[$segmentIndex]);
            if ($segmentLine === '' || Str::startsWith($segmentLine, '#')) {
                continue;
            }

            $segmentPath = $outputDir . '/' . $segmentLine;
            if (!is_file($segmentPath)) {
                continue;
            }

            $duration = $this->probeSegmentDuration($ffprobeBin, $segmentPath);
            if ($duration !== null && $duration > 0) {
                $lines[$i] = '#EXTINF:' . number_format($duration, 6, '.', '') . ',';
                $maxDuration = max($maxDuration, $duration);
                $updated = true;
            }
        }

        if ($maxDuration > 0) {
            $targetDuration = max(1, (int) ceil($maxDuration));
            foreach ($lines as $index => $line) {
                if (Str::startsWith($line, '#EXT-X-TARGETDURATION:')) {
                    $lines[$index] = '#EXT-X-TARGETDURATION:' . $targetDuration;
                    break;
                }
            }
        }

        if ($updated || $maxDuration > 0) {
            @file_put_contents($playlistPath, implode("\n", $lines) . "\n");
        }
    }

    private function probeSegmentDuration(string $ffprobeBin, string $segmentPath): ?float
    {
        $process = new Process([
            $ffprobeBin,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $segmentPath,
        ]);

        $process->setTimeout(20);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $output = trim($process->getOutput());
        $duration = (float) $output;

        return $duration > 0 ? $duration : null;
    }
    
    /**
     * Détecte le framerate du fichier source
     */
    private function detectFrameRate(string $sourcePath): int
    {
        $ffprobeBin = (string) $this->option('ffprobe-bin');
        
        $process = new Process([
            $ffprobeBin,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=r_frame_rate',
            '-of', 'csv=p=0',
            $sourcePath,
        ]);
        
        $process->setTimeout(30);
        $process->run();
        
        if (!$process->isSuccessful()) {
            return 25; // Valeur par défaut
        }
        
        $output = trim($process->getOutput());
        if (preg_match('/^(\d+)\/(\d+)$/', $output, $matches)) {
            $frameRate = (int) round((int) $matches[1] / (int) $matches[2]);
            return max(15, min(60, $frameRate)); // Sécurité
        }
        
        return 25; // Valeur par défaut
    }
    
    /**
     * Détecte le bitrate du fichier source
     */
    private function detectBitrate(string $sourcePath): int
    {
        $ffprobeBin = (string) $this->option('ffprobe-bin');
        
        $process = new Process([
            $ffprobeBin,
            '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=bit_rate',
            '-of', 'csv=p=0',
            $sourcePath,
        ]);
        
        $process->setTimeout(30);
        $process->run();
        
        if (!$process->isSuccessful()) {
            return 2500000; // Valeur par défaut
        }
        
        $output = trim($process->getOutput());
        $bitrate = (int) $output;
        
        return $bitrate > 0 ? $bitrate : 2500000;
    }
    
    /**
     * Calcule un bitrate adaptatif selon le fichier source
     */
    private function calculateAdaptiveBitrate(int $sourceBitrate, string $defaultBitrate): string
    {
        // Convertir le bitrate par défaut en nombre
        $defaultBitrateNum = (int) str_replace('k', '000', $defaultBitrate);
        
        // Bitrate adaptatif : max 10% d'augmentation du source, plafonné au défaut
        $targetBitrate = min($sourceBitrate * 1.1, $defaultBitrateNum);
        
        return round($targetBitrate / 1000) . 'k';
    }
}

