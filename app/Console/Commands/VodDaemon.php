<?php

namespace App\Console\Commands;

use App\Models\WebTVPlaylistItem;
use App\Services\AntMediaVoDService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class VodDaemon extends Command
{
    protected $signature = 'vod:daemon {--sleep=5 : Seconds to sleep between loops}';
    protected $description = 'Daemon simple pour convertir en HLS les items WebTV en attente (sans passer par la queue).';

    private string $streamsBase = '/usr/local/antmedia/webapps/LiveApp/streams';

    public function handle(): int
    {
        $sleep = (int) $this->option('sleep');
        $sleep = $sleep > 0 ? $sleep : 5;

        $this->info('VodDaemon démarré. Appuyez sur Ctrl+C pour arrêter.');

        while (true) {
            try {
                // Traiter un seul item à la fois (back-pressure)
                $item = WebTVPlaylistItem::whereIn('sync_status', ['pending', 'queued', 'processing'])
                    ->orderBy('updated_at')
                    ->first();

                if (!$item) {
                    sleep($sleep);
                    continue;
                }

                // Verrou global pour éviter concurrence multi-process
                $globalLock = Cache::lock('vod-encoder-global-lock', 1200);
                $acquiredGlobal = $globalLock->get();
                if (!$acquiredGlobal) {
                    // Attendre le prochain tour
                    sleep($sleep);
                    continue;
                }

                try {
                    // Verrou par item pour sérialiser les tentatives
                    $itemLock = Cache::lock('vod-item-lock:' . $item->id, 600);
                    $acquiredItem = $itemLock->get();
                    if (!$acquiredItem) {
                        $this->line("Item {$item->id} verrouillé, on passe.");
                        // Libérer le verrou global AVANT de passer au tour suivant
                        try { $globalLock->release(); } catch (\Throwable $e) {}
                        sleep($sleep);
                        continue;
                    }
                    try {
                        $this->processItem($item);
                    } finally {
                        try { $itemLock->release(); } catch (\Throwable $e) {}
                    }
                } finally {
                    try { $globalLock->release(); } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {
                Log::error('VodDaemon boucle: ' . $e->getMessage());
                // Eviter boucle serrée en cas d’exception
                sleep($sleep);
            }
        }
    }

    private function processItem(WebTVPlaylistItem $item): void
    {
        $item->refresh();

        if ($item->sync_status === 'synced') {
            Log::info('VodDaemon: item déjà synced - skip', ['item_id' => $item->id]);
            return;
        }

        Log::info('VodDaemon: début traitement', [
            'item_id' => $item->id,
            'video_file_id' => $item->video_file_id,
            'status' => $item->sync_status,
        ]);
        $this->line("Traitement item {$item->id} ({$item->title})");
        $item->update(['sync_status' => 'processing']);

        $vodDir = $this->streamsBase . '/vod_' . $item->id;
        // Purge avant 1re tentative
        try {
            Log::info('VodDaemon: purge initiale dossier', ['item_id' => $item->id, 'dir' => $vodDir]);
            if (File::isDirectory($vodDir)) {
                File::deleteDirectory($vodDir);
            }
        } catch (\Throwable $e) {
            Log::warning('VodDaemon purge initiale échouée: ' . $e->getMessage());
        }

        $service = new AntMediaVoDService();
        Log::info('VodDaemon: appel createVoDStream (tentative 1)', ['item_id' => $item->id]);
        $result = $service->createVoDStream($item);
        Log::info('VodDaemon: retour tentative 1', ['item_id' => $item->id, 'success' => $result['success'] ?? null]);

        // Vérification artefacts
        $ok = $this->hasMinimalArtifacts($vodDir);
        if (!($result['success'] ?? false) || !$ok) {
            // Seconde tentative après purge
            try {
                Log::warning('VodDaemon: artefacts incomplets après tentative 1, purge et retry', [
                    'item_id' => $item->id,
                    'ok' => $ok,
                    'dir' => $vodDir,
                ]);
                if (File::isDirectory($vodDir)) {
                    File::deleteDirectory($vodDir);
                }
            } catch (\Throwable $e) {
                Log::warning('VodDaemon purge retry échouée: ' . $e->getMessage());
            }
            Log::info('VodDaemon: appel createVoDStream (tentative 2)', ['item_id' => $item->id]);
            $result = $service->createVoDStream($item);
            Log::info('VodDaemon: retour tentative 2', ['item_id' => $item->id, 'success' => $result['success'] ?? null]);
            $ok = $this->hasMinimalArtifacts($vodDir);
        }

        $success = (bool) ($result['success'] ?? false);
        if ($success && $ok) {
            $item->update(['sync_status' => 'synced']);
            $this->info("Item {$item->id} => synced");
            Log::info('VodDaemon: item marqué synced', ['item_id' => $item->id]);
        } else {
            $item->update(['sync_status' => 'error']);
            Log::error('VodDaemon: conversion échouée', [
                'item_id' => $item->id,
                'result' => $result,
                'artefacts_ok' => $ok,
            ]);
        }
    }

    private function hasMinimalArtifacts(string $vodDir): bool
    {
        $playlist = $vodDir . '/playlist.m3u8';
        $s0 = $vodDir . '/segment_00000.ts';
        $s1 = $vodDir . '/segment_00001.ts';
        $s2 = $vodDir . '/segment_00002.ts';
        return File::exists($playlist) && File::exists($s0) && File::exists($s1) && File::exists($s2);
    }
}


