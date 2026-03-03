<?php

namespace App\Console\Commands;

use App\Jobs\ShakaRemuxJob;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemuxPendingVod extends Command
{
    protected $signature = 'unified-stream:remux-pending
        {--limit=5 : Nombre maximum de VOD à traiter par exécution}
        {--dry-run : N\'envoie pas les jobs, se contente de lister}
        {--force : Ignore le cache des échecs récents}';

    protected $description = 'Planifie le remux Shaka des VOD Ant Media qui ne sont pas encore convertis.';

    private string $antMediaStreamsPath = '/usr/local/antmedia/webapps/LiveApp/streams';

    private string $failureCacheKeyPrefix = 'shaka_remux_failure_';

    private int $failureCacheTtl = 900; // 15 minutes

    public function handle(): int
    {
        if (!is_dir($this->antMediaStreamsPath)) {
            $this->error('Répertoire Ant Media introuvable : ' . $this->antMediaStreamsPath);
            return self::FAILURE;
        }

        $directories = glob($this->antMediaStreamsPath . '/vod_*', GLOB_ONLYDIR) ?: [];
        if (empty($directories)) {
            $this->info('Aucun dossier VOD trouvé.');
            return self::SUCCESS;
        }

        usort($directories, static function (string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });

        $limit = max(0, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $dispatched = 0;
        $skipped = 0;

        foreach ($directories as $directory) {
            $vodId = basename($directory);

            if ($limit > 0 && $dispatched >= $limit) {
                break;
            }

            if (is_file($directory . '/master.m3u8')) {
                $skipped++;
                $this->line(sprintf('[%s] déjà converti (master.m3u8 présent)', $vodId));
                continue;
            }

            $failureKey = $this->failureCacheKeyPrefix . $vodId;
            if (!$force && Cache::has($failureKey)) {
                $skipped++;
                $this->line(sprintf('[%s] ignoré (échec récent)', $vodId));
                continue;
            }

            $playlistPath = $directory . '/playlist.m3u8';
            if (!is_file($playlistPath)) {
                $skipped++;
                $this->warn(sprintf('[%s] aucune playlist Ant Media trouvée', $vodId));
                continue;
            }

            $masterPath = $this->findMasterForVod($vodId);
            if ($masterPath === null) {
                $skipped++;
                $this->warn(sprintf('[%s] aucun master MP4 disponible. Veuillez lier le fichier dans la base.', $vodId));
                Cache::put($failureKey, true, $this->failureCacheTtl);
                continue;
            }

            if ($dryRun) {
                $this->info(sprintf('[Dry-run] %s -> job ShakaRemuxJob (master: %s)', $vodId, $masterPath));
                $dispatched++;
                continue;
            }

            ShakaRemuxJob::dispatch($vodId);
            $this->info(sprintf('Job ShakaRemuxJob dispatché pour %s', $vodId));
            $dispatched++;
        }

        $this->info(sprintf('Résultat : %d job(s) dispatché(s), %d dossier(s) ignoré(s).', $dispatched, $skipped));

        return self::SUCCESS;
    }

    private function findMasterForVod(string $vodId): ?string
    {
        $item = DB::table('webtv_playlist_items')
            ->where('ant_media_item_id', $vodId)
            ->orderByDesc('id')
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

        $fullPath = storage_path('app/media/' . ltrim((string) $media->file_path, '/'));

        if (!is_file($fullPath)) {
            return null;
        }

        return $fullPath;
    }
}


















