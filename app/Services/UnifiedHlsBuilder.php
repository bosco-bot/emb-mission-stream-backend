<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UnifiedHlsBuilder
{
    private WebTVAutoPlaylistService $autoPlaylistService;

    /**
     * Nombre maximum de segments à inclure dans la fenêtre glissante.
     * ✅ Optimisé : Augmenté de 20 à 25 pour réduire les freezes en mode VoD
     * (Buffer théorique : 40s → 50s pour segments de 2s)
     */
    private int $windowSize = 25;

    private float $minSegmentDuration = 1.0; // secondes

    /**
     * Chemin de sortie du fichier HLS unifié.
     */
    private string $outputPath = '/usr/local/antmedia/webapps/LiveApp/streams/unified.m3u8';
    private string $outputVideoPath = '/usr/local/antmedia/webapps/LiveApp/streams/unified_video.m3u8';
    private string $outputAudioPath = '/usr/local/antmedia/webapps/LiveApp/streams/unified_audio.m3u8';

    private string $sequenceStateCacheKey = 'unified_hls_sequence_state';

    private string $sequenceStatePath;

    private string $segmentMetadataPath;

    private array $segmentMetadata = [];

    private bool $segmentMetadataLoaded = false;

    private int $segmentMetadataFailureCooldown = 300;

    public function __construct()
    {
        $this->autoPlaylistService = new WebTVAutoPlaylistService();
        $this->sequenceStatePath = storage_path('app/unified_hls_sequence_state.json');
        $this->segmentMetadataPath = storage_path('app/unified_segment_metadata.json');
    }

    /**
     * Génère la playlist HLS unifiée.
     */
    public function build(): bool
    {
        $context = $this->autoPlaylistService->getCurrentPlaybackContext();

        if (($context['success'] ?? false) !== true) {
            Log::warning('❌ Impossible de générer le flux unifié - contexte indisponible', [
                'message' => $context['message'] ?? null,
            ]);

            return false;
        }

        $mode = $context['mode'] ?? 'vod';

        if ($mode === 'paused') {
            return $this->writePausedPlaylist();
        }

        if ($mode === 'live') {
            return $this->writeLiveRedirectPlaylist($context['live'] ?? []);
        }

        $timeline = $this->buildVodTimeline($context);

        if (($timeline['format'] ?? 'legacy') === 'shaka') {
            return $this->writeShakaPlaylists($timeline, $context);
        }

        $segments = $timeline['segments'] ?? [];

        if (empty($segments)) {
            Log::warning('❌ Aucun segment HLS généré pour le flux unifié');
            return false;
        }

        // ✅ Détecter la transition Live → VoD et ajouter un marqueur de discontinuité
        $state = Cache::get($this->sequenceStateCacheKey);
        if (is_array($state) && ($state['last_mode'] ?? null) === 'live' && !empty($segments)) {
            // Ajouter un marqueur de discontinuité au premier segment VoD
            $segments[0]['discontinuity'] = true;
            Log::info('🔄 Marqueur de discontinuité ajouté lors de la transition Live → VoD');
        }

        $playlistContent = $this->createPlaylist($segments);

        if ($playlistContent === '') {
            Log::warning('❌ Contenu M3U8 vide pour le flux unifié');
            return false;
        }

        $this->ensureOutputDirectory();

        if (file_put_contents($this->outputPath, $playlistContent) === false) {
            Log::error('❌ Impossible d\'écrire le fichier unified.m3u8', [
                'path' => $this->outputPath,
            ]);

            return false;
        }

        // ✅ Sauvegarder l'état pour la prochaine transition
        Cache::put($this->sequenceStateCacheKey, [
            'media_sequence' => ($segments[count($segments) - 1]['sequence'] ?? 0) + 1,
            'last_mode' => 'vod',
        ], 3600);

        Log::debug('✅ Playlist HLS unifiée mise à jour (mode legacy)', [
            'segments' => count($segments),
            'media_sequence' => $segments[0]['sequence'] ?? 0,
        ]);

        return true;
    }

    /**
     * Construit la fenêtre de segments VOD à partir du contexte courant.
     */
    private function buildVodTimeline(array $context): array
    {
        $shakaTimeline = $this->buildShakaTimeline($context);

        if ($shakaTimeline !== null) {
            return $shakaTimeline;
        }

        return [
            'format' => 'legacy',
            'segments' => $this->buildLegacyVodSegments($context),
        ];
    }

    private function buildLegacyVodSegments(array $context): array
    {
        $sequence = $context['sequence']['items'] ?? [];

        if (empty($sequence)) {
            return [];
        }

        $currentItemId = $context['current_item']['item_id'] ?? null;
        $currentTime = (float) ($context['current_item']['current_time'] ?? 0.0);
        $loopEnabled = (bool) ($context['playlist']['is_loop'] ?? false);

        if ($currentItemId === null) {
            return [];
        }

        $startIndex = $this->findItemIndex($sequence, $currentItemId);
        if ($startIndex === -1) {
            $startIndex = 0;
        }

        $segments = [];
        $itemCount = count($sequence);
        $index = $startIndex;
        $steps = 0;
        $maxSteps = $loopEnabled ? $itemCount : ($itemCount - $startIndex);
        $firstItem = true;

        while (count($segments) < $this->windowSize && $steps < max($maxSteps, 1)) {
            $item = $sequence[$index];
            $parsed = $this->loadLegacyItemSegments($item);

            if ($parsed !== null) {
                $offset = $firstItem ? $currentTime : 0.0;
                $needed = $this->windowSize - count($segments);
                $slice = $this->sliceSegments(
                    $parsed,
                    $offset,
                    $needed,
                    !$firstItem,
                    $item['item_id'] ?? null
                );
                $segments = array_merge($segments, $slice);
            }

            if (count($segments) >= $this->windowSize) {
                break;
            }

            $firstItem = false;
            $steps++;
            $index++;

            if ($index >= $itemCount) {
                if ($loopEnabled) {
                    $index = 0;
                } else {
                    break;
                }
            }

            if (!$loopEnabled && $index >= $itemCount) {
                break;
            }
        }

        return $this->applyLegacyStableSequences($segments, $context);
    }

    private function buildShakaTimeline(array $context): ?array
    {
        $sequence = $context['sequence']['items'] ?? [];

        if (empty($sequence)) {
            return null;
        }

        $currentItemId = $context['current_item']['item_id'] ?? null;
        $currentTime = (float) ($context['current_item']['current_time'] ?? 0.0);
        $loopEnabled = (bool) ($context['playlist']['is_loop'] ?? false);

        if ($currentItemId === null) {
            return null;
        }

        $startIndex = $this->findItemIndex($sequence, $currentItemId);
        if ($startIndex === -1) {
            $startIndex = 0;
        }

        $videoTimeline = [];
        $audioTimeline = [];
        $masterInfo = null;

        $itemCount = count($sequence);
        $index = $startIndex;
        $steps = 0;
        $maxSteps = $loopEnabled ? $itemCount : ($itemCount - $startIndex);
        $firstItem = true;

        while (count($videoTimeline) < $this->windowSize && $steps < max($maxSteps, 1)) {
            $item = $sequence[$index];
            $parsed = $this->loadShakaItemSegments($item);

            if ($parsed === null) {
                if (!empty($videoTimeline) || !empty($audioTimeline)) {
                    Log::debug('⚠️ Arrêt de la construction Shaka - segment non disponible', [
                        'item_id' => $item['item_id'] ?? null,
                        'ant_media_item_id' => $item['ant_media_item_id'] ?? null,
                    ]);
                    break;
                }

                return null;
            }

            if ($masterInfo === null && isset($parsed['master'])) {
                $masterInfo = $parsed['master'];
            }

            $offset = $firstItem ? $currentTime : 0.0;
            $needed = $this->windowSize - count($videoTimeline);
            $slice = $this->sliceShakaSegments(
                $parsed,
                $offset,
                $needed,
                !$firstItem
            );

            if (!empty($slice['video']) && !empty($slice['audio'])) {
                $videoTimeline = array_merge($videoTimeline, $slice['video']);
                $audioTimeline = array_merge($audioTimeline, $slice['audio']);
            }

            if (count($videoTimeline) >= $this->windowSize) {
                break;
            }

            $firstItem = false;
            $steps++;
            $index++;

            if ($index >= $itemCount) {
                if ($loopEnabled) {
                    $index = 0;
                } else {
                    break;
                }
            }

            if (!$loopEnabled && $index >= $itemCount) {
                break;
            }
        }

        if (empty($videoTimeline) || empty($audioTimeline)) {
            return null;
        }

        $tracks = $this->applyShakaStableSequences($videoTimeline, $audioTimeline, $context);

        return [
            'format' => 'shaka',
            'tracks' => $tracks,
            'master' => $masterInfo ?? [],
        ];
    }

    /**
     * Assemble la playlist HLS finale à partir des segments sélectionnés.
     */
    private function createPlaylist(array $segments, array $options = []): string
    {
        if (empty($segments)) {
            return '';
        }

        $targetDuration = 0;
        $resolvedSegments = [];

        foreach ($segments as $segment) {
            $resolvedDuration = $this->resolveSegmentDuration($segment);

            $targetDuration = max($targetDuration, $resolvedDuration);
            $resolvedSegments[] = [
                'segment' => $segment,
                'duration' => $resolvedDuration,
            ];
        }

        if ($targetDuration <= 0) {
            $targetDuration = 10;
        }

        $mediaSequence = (int) ($segments[0]['sequence'] ?? 0);

        $computedTarget = (int) ceil($targetDuration);
        $stableTarget = max(3, $computedTarget);

        if ($stableTarget !== $computedTarget) {
            Log::debug('⚙️ Ajustement du TARGETDURATION pour respecter la durée réelle des segments', [
                'computed' => $computedTarget,
                'applied' => $stableTarget,
            ]);
        }

        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-TARGETDURATION:' . $stableTarget,
            '#EXT-X-MEDIA-SEQUENCE:' . $mediaSequence,
            '#EXT-X-INDEPENDENT-SEGMENTS',
        ];

        foreach ($resolvedSegments as $wrapped) {
            $segment = $wrapped['segment'];
            $duration = $wrapped['duration'];

            if (!empty($segment['discontinuity'])) {
                $lines[] = '#EXT-X-DISCONTINUITY';
            }

            $lines[] = '#EXTINF:' . number_format($duration, 6, '.', '');
            $lines[] = $segment['uri'];
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Construit l'URL absolue d'un segment HLS.
     */
    private function makeAbsoluteUrl(string $segmentPath, ?string $antMediaItemId = null): string
    {
        if (Str::startsWith($segmentPath, ['http://', 'https://'])) {
            return $segmentPath;
        }

        if (Str::startsWith($segmentPath, '/')) {
            return 'https://tv.embmission.com' . $segmentPath;
        }

        $base = 'https://tv.embmission.com/webtv-live/streams';
        if ($antMediaItemId) {
            $base .= '/' . trim($antMediaItemId, '/');
        }

        return rtrim($base, '/') . '/' . ltrim($segmentPath, '/');
    }

    /**
     * Calcule le numéro de séquence initial de la playlist générée.
     */
    private function calculateMediaSequence(array $segments): int
    {
        if (empty($segments)) {
            return 0;
        }

        return (int) ($segments[0]['sequence'] ?? 0);
    }

    private function ensureOutputDirectory(): void
    {
        $directory = dirname($this->outputPath);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                Log::warning('⚠️ Impossible de créer le répertoire de sortie HLS', [
                    'directory' => $directory,
                ]);
            }
        }
    }

    private function writeLiveRedirectPlaylist(array $liveContext): bool
    {
        // ✅ OPTIMISATION : En mode live, unified.m3u8 est maintenant servi directement
        // par UnifiedStreamController avec validation en temps réel.
        // On n'a plus besoin de copier live_transcoded.m3u8 dans unified.m3u8.
        // On crée juste un fichier minimal pour que le système détecte le live.
        
        $source = $liveContext['source_url'] ?? null;
        $streamId = $liveContext['stream_id'] ?? null;

        if (!$source || !$streamId) {
            Log::warning('⚠️ Live détecté sans URL source ou stream_id');
            return false;
        }

        $this->ensureOutputDirectory();
        
        // ✅ Créer un fichier minimal qui pointe vers le service direct
        // Le contrôleur servira le contenu validé en temps réel
        $playlistContent = "#EXTM3U\n";
        $playlistContent .= "#EXT-X-VERSION:3\n";
        $playlistContent .= "#EXT-X-TARGETDURATION:2\n";
        $playlistContent .= "#EXT-X-MEDIA-SEQUENCE:0\n";
        $playlistContent .= "#EXT-X-PLAYLIST-TYPE:LIVE\n";
        $playlistContent .= "# Note: Ce fichier est servi dynamiquement par UnifiedStreamController\n";
        $playlistContent .= "# avec validation des segments en temps réel\n";
        
        // Sauvegarder l'état pour la prochaine transition
        Cache::put($this->sequenceStateCacheKey, [
            'last_mode' => 'live',
        ], 3600);
        
        $written = file_put_contents($this->outputPath, $playlistContent) !== false;
        
        if ($written) {
            Log::debug('✅ Fichier unified.m3u8 créé pour le live (service direct activé)', [
                'stream_id' => $streamId,
            ]);
        } else {
            Log::error('❌ Impossible d\'écrire unified.m3u8', [
                'output_path' => $this->outputPath,
            ]);
        }
        
        return $written;
    }

    private function writePausedPlaylist(): bool
    {
        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-TARGETDURATION:6',
            '#EXT-X-MEDIA-SEQUENCE:' . $this->getCurrentMediaSequence(),
            '#EXT-X-ENDLIST',
        ];

        $this->ensureOutputDirectory();

        $written = file_put_contents($this->outputPath, implode("\n", $lines) . "\n") !== false;

        if ($written) {
            Log::info('⏸️ Playlist unifiée en pause');
        }

        return $written;
    }

    private function getCurrentMediaSequence(): int
    {
        $cacheKey = $this->sequenceStateCacheKey;
        $state = Cache::get($cacheKey);

        if (is_array($state) && isset($state['media_sequence'])) {
            return (int) $state['media_sequence'];
        }

        return 0;
    }

    private function findItemIndex(array $sequence, int $itemId): int
    {
        foreach ($sequence as $index => $item) {
            if (($item['item_id'] ?? null) === $itemId) {
                return $index;
            }
        }

        return -1;
    }

    private function loadLegacyItemSegments(array $item): ?array
    {
        $antMediaItemId = $item['ant_media_item_id'] ?? null;

        if (!$antMediaItemId) {
            return null;
        }

        $basePath = '/usr/local/antmedia/webapps/LiveApp/streams/' . $antMediaItemId;
        $candidates = [
            $basePath . '/playlist.m3u8',
            $basePath . '/index.m3u8',
        ];

        $playlistPath = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $playlistPath = $candidate;
                break;
            }
        }

        if (!$playlistPath) {
            Log::warning('⚠️ Playlist HLS introuvable pour l\'item', [
                'item_id' => $item['item_id'] ?? null,
                'ant_media_item_id' => $antMediaItemId,
            ]);

            return null;
        }

        return $this->parseHlsPlaylist($playlistPath, $antMediaItemId);
    }

    private function loadShakaItemSegments(array $item): ?array
    {
        $antMediaItemId = $item['ant_media_item_id'] ?? null;

        if (!$antMediaItemId) {
            return null;
        }

        $basePath = '/usr/local/antmedia/webapps/LiveApp/streams/' . $antMediaItemId;
        $masterPath = $basePath . '/master.m3u8';

        if (!is_file($masterPath)) {
            return null;
        }

        $master = $this->parseShakaMasterPlaylist($masterPath);

        if ($master === null) {
            Log::warning('⚠️ Playlist maître Shaka invalide', [
                'item_id' => $item['item_id'] ?? null,
                'ant_media_item_id' => $antMediaItemId,
            ]);

            return null;
        }

        $audioPlaylistPath = $this->resolvePathFromUri($master['audio']['uri'] ?? null, $basePath);
        $videoPlaylistPath = $this->resolvePathFromUri($master['video']['uri'] ?? null, $basePath);

        if (!$audioPlaylistPath || !$videoPlaylistPath || !is_file($audioPlaylistPath) || !is_file($videoPlaylistPath)) {
            Log::warning('⚠️ Playlists audio/vidéo Shaka introuvables', [
                'item_id' => $item['item_id'] ?? null,
                'ant_media_item_id' => $antMediaItemId,
            ]);

            return null;
        }

        $audio = $this->parseShakaTrackPlaylist($audioPlaylistPath, $antMediaItemId);
        $video = $this->parseShakaTrackPlaylist($videoPlaylistPath, $antMediaItemId);

        if ($audio === null || $video === null) {
            return null;
        }

        return [
            'audio' => $audio,
            'video' => $video,
            'master' => $master,
        ];
    }

    private function parseHlsPlaylist(string $playlistPath, string $antMediaItemId): ?array
    {
        $content = @file($playlistPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($content === false) {
            Log::warning('⚠️ Impossible de lire la playlist HLS', [
                'path' => $playlistPath,
            ]);

            return null;
        }

        $segments = [];
        $mediaSequence = 0;
        $currentDuration = null;
        $currentProgramDateTime = null;

        foreach ($content as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-MEDIA-SEQUENCE:')) {
                $mediaSequence = (int) substr($line, strlen('#EXT-X-MEDIA-SEQUENCE:'));
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-PROGRAM-DATE-TIME:')) {
                $currentProgramDateTime = $line;
                continue;
            }

            if (Str::startsWith($line, '#EXTINF:')) {
                $durationPart = substr($line, strlen('#EXTINF:'));
                $commaPos = strpos($durationPart, ',');
                if ($commaPos !== false) {
                    $durationPart = substr($durationPart, 0, $commaPos);
                }
                $currentDuration = (float) $durationPart;
                continue;
            }

            if (Str::startsWith($line, '#')) {
                continue;
            }

            if ($currentDuration === null) {
                $currentDuration = 0.0;
            }

            $segments[] = [
                'duration' => $currentDuration,
                'uri' => $this->makeAbsoluteUrl($line, $antMediaItemId),
                'program_date_time' => $currentProgramDateTime,
            ];

            $currentDuration = null;
            $currentProgramDateTime = null;
        }

        foreach ($segments as $index => &$segment) {
            $segment['sequence'] = $mediaSequence + $index;
        }

        return [
            'segments' => $segments,
            'media_sequence' => $mediaSequence,
        ];
    }

    private function parseShakaMasterPlaylist(string $masterPath): ?array
    {
        $lines = @file($masterPath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return null;
        }

        $info = [
            'audio' => null,
            'video' => null,
            'independent_segments' => false,
        ];

        $pendingStream = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || Str::startsWith($line, '##')) {
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-INDEPENDENT-SEGMENTS')) {
                $info['independent_segments'] = true;
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-MEDIA:')) {
                $attributes = $this->parseAttributeList($line);
                if (($attributes['TYPE'] ?? '') === 'AUDIO') {
                    $info['audio'] = [
                        'uri' => $attributes['URI'] ?? null,
                        'group_id' => $attributes['GROUP-ID'] ?? 'default-audio-group',
                        'language' => $attributes['LANGUAGE'] ?? null,
                        'name' => $attributes['NAME'] ?? 'audio',
                        'default' => $attributes['DEFAULT'] ?? 'YES',
                        'autoselect' => $attributes['AUTOSELECT'] ?? 'YES',
                        'channels' => $attributes['CHANNELS'] ?? null,
                    ];
                }
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-STREAM-INF:')) {
                $pendingStream = $this->parseAttributeList($line);
                continue;
            }

            if ($pendingStream !== null && !Str::startsWith($line, '#')) {
                $info['video'] = array_merge($pendingStream, [
                    'uri' => $line,
                ]);
                $pendingStream = null;
                continue;
            }
        }

        if ($info['audio'] === null || $info['video'] === null) {
            return null;
        }

        return $info;
    }

    private function parseShakaTrackPlaylist(string $playlistPath, string $antMediaItemId): ?array
    {
        $lines = @file($playlistPath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            Log::warning('⚠️ Impossible de lire la playlist Shaka', [
                'path' => $playlistPath,
            ]);

            return null;
        }

        $segments = [];
        $mediaSequence = 0;
        $currentDuration = null;
        $discontinuityPending = false;
        $initUri = null;
        $version = 6;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-VERSION:')) {
                $parsedVersion = (int) substr($line, strlen('#EXT-X-VERSION:'));
                if ($parsedVersion > 0) {
                    $version = max(6, $parsedVersion);
                }
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-MEDIA-SEQUENCE:')) {
                $mediaSequence = (int) substr($line, strlen('#EXT-X-MEDIA-SEQUENCE:'));
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-MAP:')) {
                $attributes = $this->parseAttributeList($line);
                $initUri = $attributes['URI'] ?? null;
                continue;
            }

            if (Str::startsWith($line, '#EXT-X-DISCONTINUITY')) {
                $discontinuityPending = true;
                continue;
            }

            if (Str::startsWith($line, '#EXTINF:')) {
                $durationPart = substr($line, strlen('#EXTINF:'));
                $commaPos = strpos($durationPart, ',');
                if ($commaPos !== false) {
                    $durationPart = substr($durationPart, 0, $commaPos);
                }
                $currentDuration = (float) $durationPart;
                continue;
            }

            if (Str::startsWith($line, '#')) {
                continue;
            }

            $segmentDuration = $currentDuration ?? 0.0;
            $absoluteUri = $this->makeAbsoluteUrl($line, $antMediaItemId);
            $absoluteInit = $initUri ? $this->makeAbsoluteUrl($initUri, $antMediaItemId) : null;

            $segments[] = [
                'duration' => $segmentDuration,
                'uri' => $absoluteUri,
                'init_uri' => $absoluteInit,
                'discontinuity' => $discontinuityPending,
            ];

            $currentDuration = null;
            $discontinuityPending = false;
        }

        if (empty($segments) || $initUri === null) {
            Log::warning('⚠️ Playlist Shaka sans segments ou init', [
                'path' => $playlistPath,
            ]);

            return null;
        }

        foreach ($segments as $index => &$segment) {
            $segment['sequence'] = $mediaSequence + $index;
        }
        unset($segment);

        return [
            'segments' => $segments,
            'media_sequence' => $mediaSequence,
            'version' => $version,
        ];
    }

    private function sliceShakaSegments(array $parsed, float $offsetSeconds, int $needed, bool $discontinuity): array
    {
        $videoSegments = $parsed['video']['segments'] ?? [];
        $audioSegments = $parsed['audio']['segments'] ?? [];

        $resultVideo = [];
        $resultAudio = [];
        $cumulative = 0.0;

        $count = min(count($videoSegments), count($audioSegments));

        for ($i = 0; $i < $count; $i++) {
            $video = $videoSegments[$i];
            $audio = $audioSegments[$i];

            $duration = (float) ($video['duration'] ?? 0.0);
            $start = $cumulative;
            $end = $start + $duration;

            if ($end <= $offsetSeconds) {
                $cumulative = $end;
                continue;
            }

            if ($duration < $this->minSegmentDuration) {
                Log::debug('⚠️ Segment CMAF ignoré car trop court', [
                    'video_uri' => $video['uri'] ?? null,
                    'duration' => $duration,
                ]);
                $cumulative = $end;
                $discontinuity = true;
                continue;
            }

            $videoCopy = $video;
            $audioCopy = $audio;

            if ($discontinuity) {
                $videoCopy['discontinuity'] = true;
                $audioCopy['discontinuity'] = true;
                $discontinuity = false;
            }

            $resultVideo[] = $videoCopy;
            $resultAudio[] = $audioCopy;

            if (count($resultVideo) >= $needed) {
                break;
            }

            $cumulative = $end;
        }

        return [
            'video' => $resultVideo,
            'audio' => $resultAudio,
        ];
    }

    private function applyShakaStableSequences(array $videoSegments, array $audioSegments, array $context): array
    {
        $state = $this->loadSequenceState();

        if ($this->shouldResetSequences($state, $context)) {
            $state['tracks']['video']['uri_map'] = [];
            $state['tracks']['video']['last_sequence'] = -1;
            $state['tracks']['audio']['uri_map'] = [];
            $state['tracks']['audio']['last_sequence'] = -1;
        }

        $videoResult = $this->applyTrackStableSequences($videoSegments, $state['tracks']['video'] ?? []);
        $audioResult = $this->applyTrackStableSequences($audioSegments, $state['tracks']['audio'] ?? []);

        $state['tracks']['video'] = $videoResult['state'];
        $state['tracks']['audio'] = $audioResult['state'];

        $currentItemId = $context['current_item']['item_id'] ?? null;
        $currentItemTime = (float) ($context['current_item']['current_time'] ?? 0.0);
        $currentItemIndex = $this->findItemIndex($context['sequence']['items'] ?? [], $currentItemId ?? 0);

        $state['last_item_id'] = $currentItemId;
        $state['last_item_time'] = $currentItemTime;
        $state['last_item_index'] = $currentItemIndex;
        $state['updated_at'] = time();
        $state['media_sequence'] = $videoResult['media_sequence'];

        $this->saveSequenceState($state);

        return [
            'video' => [
                'segments' => $videoResult['segments'],
                'media_sequence' => $videoResult['media_sequence'],
            ],
            'audio' => [
                'segments' => $audioResult['segments'],
                'media_sequence' => $audioResult['media_sequence'],
            ],
        ];
    }

    /**
     * @return array{segments: array<int, array<string, mixed>>, state: array<string, mixed>, media_sequence: int}
     */
    private function applyTrackStableSequences(array $segments, array $trackState): array
    {
        $lastSequence = (int) ($trackState['last_sequence'] ?? -1);
        $uriMap = is_array($trackState['uri_map'] ?? null) ? $trackState['uri_map'] : [];
        $newUriMap = [];

        foreach ($segments as &$segment) {
            $uri = $segment['uri'] ?? null;
            if (!$uri) {
                continue;
            }

            if (isset($uriMap[$uri]['sequence'])) {
                $sequence = (int) $uriMap[$uri]['sequence'];
            } else {
                $sequence = $lastSequence + 1;
                $lastSequence = $sequence;
            }

            $segment['sequence'] = $sequence;
            $newUriMap[$uri] = [
                'sequence' => $sequence,
                'last_seen_at' => time(),
            ];
        }
        unset($segment);

        $mediaSequence = (int) ($segments[0]['sequence'] ?? 0);

        return [
            'segments' => $segments,
            'media_sequence' => $mediaSequence,
            'state' => [
                'last_sequence' => $lastSequence,
                'uri_map' => array_slice($newUriMap, -500, null, true),
            ],
        ];
    }

    private function writeShakaPlaylists(array $timeline, array $context): bool
    {
        $videoTrack = $timeline['tracks']['video']['segments'] ?? [];
        $audioTrack = $timeline['tracks']['audio']['segments'] ?? [];

        if (empty($videoTrack) || empty($audioTrack)) {
            Log::warning('⚠️ Impossible d\'écrire les playlists Shaka - segments manquants');
            return false;
        }

        $masterInfo = $timeline['master'] ?? [];

        $masterContent = $this->createShakaMasterPlaylist($masterInfo);
        $videoContent = $this->createCmafPlaylist($videoTrack, [
            'media_sequence' => $timeline['tracks']['video']['media_sequence'] ?? 0,
        ]);
        $audioContent = $this->createCmafPlaylist($audioTrack, [
            'media_sequence' => $timeline['tracks']['audio']['media_sequence'] ?? 0,
        ]);

        if ($masterContent === '' || $videoContent === '' || $audioContent === '') {
            Log::warning('⚠️ Contenu HLS Shaka vide');
            return false;
        }

        $this->ensureOutputDirectory();

        $writes = [
            [$this->outputPath, $masterContent],
            [$this->outputVideoPath, $videoContent],
            [$this->outputAudioPath, $audioContent],
        ];

        foreach ($writes as [$path, $content]) {
            if (file_put_contents($path, $content) === false) {
                Log::error('❌ Échec d\'écriture de la playlist Shaka', [
                    'path' => $path,
                ]);
                return false;
            }
        }

        Log::debug('✅ Playlists HLS unifiées (Shaka) mises à jour', [
            'segments' => count($videoTrack),
            'media_sequence_video' => $videoTrack[0]['sequence'] ?? 0,
            'media_sequence_audio' => $audioTrack[0]['sequence'] ?? 0,
        ]);

        return true;
    }

    private function createCmafPlaylist(array $segments, array $options = []): string
    {
        if (empty($segments)) {
            return '';
        }

        $targetDuration = 0.0;
        $resolvedSegments = [];

        foreach ($segments as $segment) {
            $duration = $this->resolveSegmentDuration($segment);
            $targetDuration = max($targetDuration, $duration);
            $resolvedSegments[] = [
                'segment' => $segment,
                'duration' => $duration,
            ];
        }

        if ($targetDuration <= 0) {
            $targetDuration = 10;
        }

        $mediaSequence = (int) ($segments[0]['sequence'] ?? ($options['media_sequence'] ?? 0));

        $computedTarget = (int) ceil($targetDuration);
        $stableTarget = max(3, $computedTarget);

        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:6',
            '#EXT-X-TARGETDURATION:' . $stableTarget,
            '#EXT-X-MEDIA-SEQUENCE:' . $mediaSequence,
            '#EXT-X-INDEPENDENT-SEGMENTS',
        ];

        $currentMap = null;

        foreach ($resolvedSegments as $wrapped) {
            $segment = $wrapped['segment'];
            $duration = $wrapped['duration'];
            $mapUri = $segment['init_uri'] ?? null;

            if ($mapUri && $mapUri !== $currentMap) {
                $lines[] = '#EXT-X-MAP:URI="' . $mapUri . '"';
                $currentMap = $mapUri;
            }

            if (!empty($segment['discontinuity'])) {
                $lines[] = '#EXT-X-DISCONTINUITY';
            }

            $lines[] = '#EXTINF:' . number_format($duration, 6, '.', '');
            $lines[] = $segment['uri'];
        }

        return implode("\n", $lines) . "\n";
    }

    private function createShakaMasterPlaylist(array $masterInfo): string
    {
        $audio = $masterInfo['audio'] ?? [];
        $video = $masterInfo['video'] ?? [];

        $audioGroupId = $audio['group_id'] ?? 'default-audio-group';

        $audioParts = [
            'TYPE=AUDIO',
            'URI="unified_audio.m3u8"',
            'GROUP-ID="' . $audioGroupId . '"',
            'NAME="' . ($audio['name'] ?? 'audio') . '"',
            'DEFAULT=' . ($audio['default'] ?? 'YES'),
            'AUTOSELECT=' . ($audio['autoselect'] ?? 'YES'),
        ];

        if (!empty($audio['language'])) {
            $audioParts[] = 'LANGUAGE="' . $audio['language'] . '"';
        }

        if (!empty($audio['channels'])) {
            $audioParts[] = 'CHANNELS="' . $audio['channels'] . '"';
        }

        $videoParts = [
            'BANDWIDTH=' . (int) ($video['BANDWIDTH'] ?? 3500000),
        ];

        if (!empty($video['AVERAGE-BANDWIDTH'])) {
            $videoParts[] = 'AVERAGE-BANDWIDTH=' . (int) $video['AVERAGE-BANDWIDTH'];
        }

        if (!empty($video['CODECS'])) {
            $videoParts[] = 'CODECS="' . $video['CODECS'] . '"';
        }

        if (!empty($video['RESOLUTION'])) {
            $videoParts[] = 'RESOLUTION=' . $video['RESOLUTION'];
        }

        if (!empty($video['FRAME-RATE'])) {
            $videoParts[] = 'FRAME-RATE=' . $video['FRAME-RATE'];
        }

        $videoParts[] = 'AUDIO="' . $audioGroupId . '"';
        $videoParts[] = 'CLOSED-CAPTIONS=NONE';

        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:6',
            '#EXT-X-INDEPENDENT-SEGMENTS',
            '#EXT-X-MEDIA:' . implode(',', $audioParts),
            '#EXT-X-STREAM-INF:' . implode(',', $videoParts),
            'unified_video.m3u8',
        ];

        return implode("\n", $lines) . "\n";
    }

    private function resolvePathFromUri(?string $uri, string $basePath): ?string
    {
        if (!$uri) {
            return null;
        }

        if (Str::startsWith($uri, ['http://', 'https://'])) {
            return null;
        }

        if (Str::startsWith($uri, '/')) {
            if (is_file($uri)) {
                return $uri;
            }

            return rtrim($basePath, '/') . '/' . ltrim($uri, '/');
        }

        return rtrim($basePath, '/') . '/' . ltrim($uri, '/');
    }

    private function parseAttributeList(string $line): array
    {
        $result = [];
        $colonPos = strpos($line, ':');
        if ($colonPos === false) {
            return $result;
        }

        $attributes = substr($line, $colonPos + 1);

        if ($attributes === false || $attributes === '') {
            return $result;
        }

        if (!preg_match_all('/([A-Z0-9\\-]+)=("([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|[^,]*)/', $attributes, $matches, PREG_SET_ORDER)) {
            return $result;
        }

        foreach ($matches as $match) {
            $key = $match[1];
            $value = $match[2] ?? '';
            if (Str::startsWith($value, '"') && Str::endsWith($value, '"')) {
                $value = substr($value, 1, -1);
            }
            $result[$key] = $value;
        }

        return $result;
    }

    private function sliceSegments(array $parsed, float $offsetSeconds, int $needed, bool $discontinuity, ?int $itemId = null): array
    {
        $result = [];
        $segments = $parsed['segments'] ?? [];
        $cumulative = 0.0;

        foreach ($segments as $segment) {
            $start = $cumulative;
            $end = $start + (float) ($segment['duration'] ?? 0);

            if ($end <= $offsetSeconds) {
                $cumulative = $end;
                continue;
            }

            $duration = (float) ($segment['duration'] ?? 0);
            if ($duration < $this->minSegmentDuration) {
                Log::debug('⚠️ Segment HLS ignoré car trop court', [
                    'uri' => $segment['uri'] ?? null,
                    'duration' => $duration,
                ]);
                $cumulative = $end;
                $discontinuity = true; // forcer discontinuité sur prochain segment gardé
                continue;
            }

            $segmentCopy = $segment;

            if ($discontinuity) {
                $segmentCopy['discontinuity'] = true;
                $discontinuity = false;
            }

            $result[] = $segmentCopy;

            if (count($result) >= $needed) {
                break;
            }

            $cumulative = $end;
        }

        return $result;
    }

    private function applyLegacyStableSequences(array $segments, array $context): array
    {
        if (empty($segments)) {
            return $segments;
        }

        $state = $this->loadSequenceState();

        $legacyState = $state['legacy'];
        $lastSequence = (int) ($legacyState['last_sequence'] ?? -1);
        $uriMap = is_array($legacyState['uri_map'] ?? null) ? $legacyState['uri_map'] : [];

        if ($this->shouldResetSequences($state, $context)) {
            $uriMap = [];
            $lastSequence = -1;
        }

        $newUriMap = [];

        foreach ($segments as &$segment) {
            $uri = $segment['uri'] ?? null;
            if (!$uri) {
                continue;
            }

            if (isset($uriMap[$uri]['sequence'])) {
                $sequence = (int) $uriMap[$uri]['sequence'];
            } else {
                $sequence = $lastSequence + 1;
                $lastSequence = $sequence;
            }

            $segment['sequence'] = $sequence;
            $newUriMap[$uri] = [
                'sequence' => $sequence,
                'last_seen_at' => time(),
            ];
        }
        unset($segment);

        $currentItemId = $context['current_item']['item_id'] ?? null;
        $currentItemTime = (float) ($context['current_item']['current_time'] ?? 0.0);
        $currentItemIndex = $this->findItemIndex($context['sequence']['items'] ?? [], $currentItemId ?? 0);

        $state['legacy']['last_sequence'] = $lastSequence;
        $state['legacy']['uri_map'] = array_slice($newUriMap, -500, null, true);
        $state['last_item_id'] = $currentItemId;
        $state['last_item_time'] = $currentItemTime;
        $state['last_item_index'] = $currentItemIndex;
        $state['updated_at'] = time();
        $state['media_sequence'] = (int) ($segments[0]['sequence'] ?? 0);

        $this->saveSequenceState($state);

        return $segments;
    }

    private function loadSequenceState(): array
    {
        $cached = Cache::get($this->sequenceStateCacheKey);

        if (is_array($cached)) {
            return $this->ensureSequenceStateDefaults($cached);
        }

        $path = $this->sequenceStatePath;
        if (is_readable($path)) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $decoded = $this->ensureSequenceStateDefaults($decoded);
                    Cache::put($this->sequenceStateCacheKey, $decoded, now()->addHours(6));
                    return $decoded;
                }
            }
        }

        return $this->ensureSequenceStateDefaults([]);
    }

    private function saveSequenceState(array $state): void
    {
        $state = $this->ensureSequenceStateDefaults($state);

        Cache::put($this->sequenceStateCacheKey, $state, now()->addHours(6));

        $directory = dirname($this->sequenceStatePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($this->sequenceStatePath, json_encode($state, JSON_PRETTY_PRINT));
    }

    private function ensureSequenceStateDefaults(array $state): array
    {
        $migratedState = $state;

        // Migration depuis l'ancien format (legacy uniquement).
        if (isset($migratedState['last_sequence']) && !isset($migratedState['legacy'])) {
            $migratedState['legacy'] = [
                'last_sequence' => (int) $migratedState['last_sequence'],
                'uri_map' => $migratedState['uri_map'] ?? [],
            ];
            unset($migratedState['last_sequence'], $migratedState['uri_map']);
        }

        $migratedState['legacy'] = $this->ensureTrackStateDefaults($migratedState['legacy'] ?? null);

        $tracks = $migratedState['tracks'] ?? [];
        $migratedState['tracks'] = [
            'video' => $this->ensureTrackStateDefaults($tracks['video'] ?? null),
            'audio' => $this->ensureTrackStateDefaults($tracks['audio'] ?? null),
        ];

        $migratedState['last_item_id'] = $migratedState['last_item_id'] ?? null;
        $migratedState['last_item_time'] = isset($migratedState['last_item_time']) ? (float) $migratedState['last_item_time'] : 0.0;
        $migratedState['last_item_index'] = isset($migratedState['last_item_index']) ? (int) $migratedState['last_item_index'] : -1;
        $migratedState['updated_at'] = isset($migratedState['updated_at']) ? (int) $migratedState['updated_at'] : 0;
        $migratedState['media_sequence'] = isset($migratedState['media_sequence']) ? (int) $migratedState['media_sequence'] : 0;

        return $migratedState;
    }

    /**
     * @return array{last_sequence: int, uri_map: array<string, array<string, int>>}
     */
    private function ensureTrackStateDefaults(?array $trackState): array
    {
        $trackState = $trackState ?? [];
        $lastSequence = isset($trackState['last_sequence']) ? (int) $trackState['last_sequence'] : -1;
        $uriMap = $this->normalizeUriMap($trackState['uri_map'] ?? []);

        return [
            'last_sequence' => $lastSequence,
            'uri_map' => $uriMap,
        ];
    }

    /**
     * @param array<string, mixed> $uriMap
     * @return array<string, array<string, int>>
     */
    private function normalizeUriMap(array $uriMap): array
    {
        $normalized = [];

        foreach ($uriMap as $uri => $entry) {
            if (is_numeric($entry)) {
                $normalized[$uri] = [
                    'sequence' => (int) $entry,
                    'last_seen_at' => time(),
                ];
                continue;
            }

            if (is_array($entry)) {
                $normalized[$uri] = [
                    'sequence' => (int) ($entry['sequence'] ?? -1),
                    'last_seen_at' => (int) ($entry['last_seen_at'] ?? time()),
                ];
            }
        }

        return $normalized;
    }

    private function shouldResetSequences(array $state, array $context): bool
    {
        $currentItemId = $context['current_item']['item_id'] ?? null;
        $currentTime = (float) ($context['current_item']['current_time'] ?? 0.0);

        if ($currentItemId === null) {
            return false;
        }

        $lastItemId = $state['last_item_id'] ?? null;
        $lastItemTime = (float) ($state['last_item_time'] ?? 0.0);
        $lastItemIndex = (int) ($state['last_item_index'] ?? -1);

        $sequenceItems = $context['sequence']['items'] ?? [];
        $currentIndex = $this->findItemIndex($sequenceItems, $currentItemId);

        if ($lastItemId === null) {
            return false;
        }

        if ($currentItemId === $lastItemId) {
            if ($currentTime + 0.5 < $lastItemTime) {
                return true;
            }

            if ($currentTime < 1 && $lastItemTime > 5) {
                return true;
            }

            return false;
        }

        if ($currentIndex === -1 || $lastItemIndex === -1) {
            return false;
        }

        if ($currentIndex + 1 < $lastItemIndex) {
            return true;
        }

        return false;
    }

    private function resolveSegmentDuration(array $segment): float
    {
        $fallback = max((float) ($segment['duration'] ?? 0), $this->minSegmentDuration);
        $uri = $segment['uri'] ?? null;

        if (!$uri) {
            return $fallback;
        }

        $metadata = $this->loadSegmentMetadata();

        if (isset($metadata[$uri]['duration']) && (float) $metadata[$uri]['duration'] > 0) {
            return (float) $metadata[$uri]['duration'];
        }

        if (isset($metadata[$uri]['last_failed_at'])) {
            $lastFailedAt = (int) $metadata[$uri]['last_failed_at'];
            if ($lastFailedAt > 0 && (time() - $lastFailedAt) < $this->segmentMetadataFailureCooldown) {
                return $fallback;
            }
        }

        $duration = $this->probeSegmentDuration($uri);

        if ($duration > 0) {
            $this->segmentMetadata[$uri] = [
                'duration' => $duration,
                'measured_at' => time(),
            ];

            $this->saveSegmentMetadata();

            return $duration;
        }

        $this->segmentMetadata[$uri]['last_failed_at'] = time();
        $this->saveSegmentMetadata();

        return $fallback;
    }

    private function probeSegmentDuration(string $uri): float
    {
        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellarg($uri)
        );

        $output = @shell_exec($command);

        if ($output === null) {
            Log::warning('⚠️ Impossible de mesurer la durée du segment (ffprobe non disponible ?)', [
                'uri' => $uri,
            ]);

            return 0.0;
        }

        $duration = (float) trim($output);

        if ($duration <= 0) {
            Log::warning('⚠️ ffprobe n\'a pas retourné de durée valide pour le segment', [
                'uri' => $uri,
                'output' => $output,
            ]);
        }

        return $duration;
    }

    private function loadSegmentMetadata(): array
    {
        if ($this->segmentMetadataLoaded) {
            return $this->segmentMetadata;
        }

        $path = $this->segmentMetadataPath;

        if (is_readable($path)) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->segmentMetadata = $decoded;
                }
            }
        }

        $this->segmentMetadataLoaded = true;

        return $this->segmentMetadata;
    }

    private function saveSegmentMetadata(): void
    {
        if (!$this->segmentMetadataLoaded) {
            return;
        }

        $directory = dirname($this->segmentMetadataPath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($this->segmentMetadataPath, json_encode($this->segmentMetadata, JSON_PRETTY_PRINT));
    }
}

