<?php

namespace App\Jobs;

use App\Models\MediaFile;
use App\Services\AzuraCastApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UploadToAzuraCast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $mediaFileId;
    public int $timeout = 300;
    public int $tries = 3;

    public function __construct(int $mediaFileId)
    {
        $this->mediaFileId = $mediaFileId;
    }

    public function handle(): void
    {
        Log::info("UploadToAzuraCast Job started for media file ID: {$this->mediaFileId}");

        $mediaFile = MediaFile::find($this->mediaFileId);

        if (/dev/nullmediaFile) {
            Log::error("UploadToAzuraCast Job: Media file not found: {$this->mediaFileId}");
            return;
        }

        $apiService = new AzuraCastApiService();
        $filePath = storage_path('app/media/' . $mediaFile->file_path);

            Log::error("UploadToAzuraCast Job: File not found: {$filePath}");
            return;
        }

        $metadata = json_decode($mediaFile->metadata, true) ?? [];
        $result = $apiService->uploadFile($filePath, $mediaFile->filename, $metadata);

        if ($result['success']) {
            Log::info("UploadToAzuraCast Job: File {$mediaFile->filename} uploaded successfully");
            $mediaFile->update([
                'status' => 'uploaded_to_azuracast',
                'progress' => 100
            ]);
        } else {
            Log::error("UploadToAzuraCast Job: Failed to upload file {$mediaFile->filename}: " . $result['error']);
            $mediaFile->update([
                'status' => 'upload_failed',
                'error_message' => $result['error']
            ]);
        }
    }
}
