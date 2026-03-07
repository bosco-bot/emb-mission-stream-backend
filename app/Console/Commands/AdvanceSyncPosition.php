<?php

namespace App\Console\Commands;

use App\Services\WebTVAutoPlaylistService;
use Illuminate\Console\Command;

class AdvanceSyncPosition extends Command
{
    protected $signature = 'webtv:advance-sync-position';

    protected $description = 'Avance la position de lecture du flux unifié (chaîne TV) selon le temps écoulé';

    public function handle(WebTVAutoPlaylistService $autoPlaylistService): int
    {
        $result = $autoPlaylistService->advanceSyncPosition();

        if ($result['success'] ?? false) {
            return self::SUCCESS;
        }

        $this->warn($result['message'] ?? 'Aucune mise à jour');
        return self::SUCCESS;
    }
}