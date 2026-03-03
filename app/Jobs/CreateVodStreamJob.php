<?php

namespace App\Jobs;

use App\Models\WebTVPlaylistItem;
use App\Services\AntMediaPlaylistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

class CreateVodStreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Nombre de tentatives et stratégie de backoff pour limiter la charge.
     */
    public int $tries = 3;
    public $backoff = [60, 180, 300]; // 1 min, 3 min, 5 min
    public int $timeout = 900; // 15 minutes max par job

    /**
     * Identifiant de l'item playlist à traiter.
     */
    public int $playlistItemId;

    /**
     * Crée une nouvelle instance du job.
     */
    public function __construct(int $playlistItemId)
    {
        $this->playlistItemId = $playlistItemId;
    }

    /**
     * Exécute la conversion VoD de manière asynchrone.
     */
    public function handle(): void
    {
        $item = WebTVPlaylistItem::find($this->playlistItemId);

        if (!$item) {
            Log::warning('🎬 VoD async: item introuvable, abandon du job', [
                'playlist_item_id' => $this->playlistItemId,
            ]);
            return;
        }

        if (!$item->video_file_id) {
            Log::warning('🎬 VoD async: item sans fichier vidéo, rien à faire', [
                'playlist_item_id' => $this->playlistItemId,
            ]);
            return;
        }

        if ($item->sync_status === 'synced') {
            Log::info('🎬 VoD async: item déjà synchronisé, saut', [
                'playlist_item_id' => $item->id,
            ]);
            return;
        }

        Log::info('🎬 VoD async: lancement de la conversion', [
            'playlist_item_id' => $item->id,
            'video_file_id' => $item->video_file_id,
        ]);

        $item->update([
            'sync_status' => 'processing',
        ]);

        // Verrou global pour limiter la concurrence FFmpeg (back-pressure)
        // Empêche plusieurs conversions lourdes simultanées (ex: 1 à la fois)
        // TTL du verrou > timeout job pour éviter les fuites de verrou
        $lock = Cache::lock('vod-encoder-global-lock', 1200); // 20 minutes
        $result = ['success' => false, 'message' => 'lock not acquired'];
        $acquired = false;
        try {
            // Attente bloquante maximale avant d'abandonner la tentative (<= backoff step)
            $lock->block(300, function () use (&$result, $item) {
        $service = new AntMediaPlaylistService();
                $local = $service->addItemToPlaylist($item);

                // Vérification artefacts HLS; si incomplets, purge et seconde tentative locale
                $vodDir = '/usr/local/antmedia/webapps/LiveApp/streams/vod_' . $item->id;
                $playlistPath = $vodDir . '/playlist.m3u8';
                $haveFirstSegments = File::exists($vodDir . '/segment_00000.ts')
                    && File::exists($vodDir . '/segment_00001.ts')
                    && File::exists($vodDir . '/segment_00002.ts');

                if (!($local['success'] ?? false) || !File::exists($playlistPath) || !$haveFirstSegments) {
                    try {
                        if (File::isDirectory($vodDir)) {
                            File::deleteDirectory($vodDir);
                        }
                    } catch (Throwable $e) {
                        Log::warning('⚠️ Purge vod avant retry échouée', [
                            'playlist_item_id' => $item->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $local = $service->addItemToPlaylist($item);
                }

                $result = $local;
            });
            $acquired = true;
        } catch (Throwable $e) {
            Log::warning('⚠️ VoD async: impossible d\'acquérir le verrou global', [
                'playlist_item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if ($acquired) {
                try { $lock->release(); } catch (Throwable $e) {
                    Log::warning('⚠️ VoD async: libération verrou global échouée', [
                        'playlist_item_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $success = (bool) ($result['success'] ?? false);

        $item->update([
            'sync_status' => $success ? 'synced' : 'error',
        ]);

        if ($success) {
            Log::info('✅ VoD async: conversion terminée', [
                'playlist_item_id' => $item->id,
            ]);
        } else {
            Log::error('❌ VoD async: conversion échouée', [
                'playlist_item_id' => $item->id,
                'result' => $result,
            ]);
        }
    }

    /**
     * Gestion des échecs irrécupérables du job.
     */
    public function failed(Throwable $exception): void
    {
        if ($item = WebTVPlaylistItem::find($this->playlistItemId)) {
            $item->update(['sync_status' => 'error']);
        }

        Log::error('❌ VoD async: job en échec', [
            'playlist_item_id' => $this->playlistItemId,
            'error' => $exception->getMessage(),
        ]);
    }
}





