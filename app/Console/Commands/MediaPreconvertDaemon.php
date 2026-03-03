<?php

namespace App\Console\Commands;

use App\Models\MediaFile;
use App\Services\AntMediaVoDService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Throwable;

class MediaPreconvertDaemon extends Command
{
    protected $signature = 'media:preconvert-daemon {--sleep=10}';
    protected $description = 'Pré-convertit les MediaFile en HLS (vod_mf_{id}) en continu.';

    private string $streamsBase = '/usr/local/antmedia/webapps/LiveApp/streams';

    public function handle(): int
    {
        $sleep = (int) $this->option('sleep');
        $sleep = $sleep > 0 ? $sleep : 10;

        $this->info('MediaPreconvertDaemon démarré.');

        while (true) {
            try {
                $mf = $this->nextUnconvertedMediaFile();
                if (!$mf) {
                    sleep($sleep);
                    continue;
                }
                $vodService = new AntMediaVoDService();
                $vodName = $vodService->buildVodNameForMediaFile($mf->id);
                $vodDir = $this->streamsBase . '/' . $vodName;
                // Purge défensive si dossier présent mais incomplet
                try {
                    if (File::isDirectory($vodDir)) {
                        File::deleteDirectory($vodDir);
                    }
                } catch (Throwable $e) {
                    Log::warning('MediaPreconvert purge échouée: ' . $e->getMessage(), ['media_file_id' => $mf->id]);
                }
                $res = $vodService->createVodForMediaFile($mf);
                Log::info('MediaPreconvert: résultat', ['media_file_id' => $mf->id, 'success' => $res['success'] ?? null]);
            } catch (Throwable $e) {
                Log::error('MediaPreconvert boucle: ' . $e->getMessage());
                sleep($sleep);
            }
        }
    }

    private function nextUnconvertedMediaFile(): ?MediaFile
    {
        $candidates = MediaFile::orderBy('updated_at', 'asc')->limit(50)->get();
        $vodService = new AntMediaVoDService();
        foreach ($candidates as $mf) {
            $vodName = $vodService->buildVodNameForMediaFile($mf->id);
            $playlist = $this->streamsBase . '/' . $vodName . '/playlist.m3u8';
            $source = storage_path('app/media/' . $mf->file_path);
            if (file_exists($source) && !file_exists($playlist)) {
                return $mf;
            }
        }
        return null;
    }
}



