<?php

namespace App\Console\Commands;

use App\Models\WebTVPlaylistItem;
use App\Services\AntMediaVoDService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LinkPendingPreconverted extends Command
{
    protected $signature = 'webtv:link-preconverted {--limit=50}';
    protected $description = 'Lie automatiquement les items WebTV en pending aux HLS pré-convertis (vod_mf_{video_file_id}) si disponibles.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit') ?: 50;
        $vodService = new AntMediaVoDService();
        $countLinked = 0;
        $countChecked = 0;

        $items = WebTVPlaylistItem::where('sync_status', 'pending')
            ->whereNotNull('video_file_id')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($items as $item) {
            $countChecked++;
            $vodName = $vodService->buildVodNameForMediaFile((int) $item->video_file_id);
            // Ne lier que si le HLS est complet (ENDLIST + segments suffisants)
            if ($vodService->isVodComplete($vodName)) {
                $streamUrl = $vodService->buildHlsUrlFromVodName($vodName);
                $item->update([
                    'ant_media_item_id' => $vodName,
                    'stream_url' => $streamUrl,
                    'sync_status' => 'synced',
                ]);
                $countLinked++;
                Log::info('webtv:link-preconverted -> linked', [
                    'item_id' => $item->id,
                    'vod_name' => $vodName,
                ]);
                $this->line("Linked item {$item->id} -> {$vodName}");
            }
        }

        $this->info("Checked: {$countChecked}, Linked: {$countLinked}");
        return self::SUCCESS;
    }
}


