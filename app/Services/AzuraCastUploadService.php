<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzuraCastUploadService
{
    private string $baseUrl;
    private string $apiKey;
    private int $stationId;

    public function __construct()
    {
        $this->baseUrl = env('AZURACAST_BASE_URL', 'http://15.235.86.98:8080');
        $this->apiKey = env('AZURACAST_API_KEY', '4c1ffa679f50abe5:2e5149b9a2a11f310f46e4ccfce73cd8');
        $this->stationId = env('AZURACAST_STATION_ID', 1);
    }

    public function uploadFile(string $filePath, string $filename): array
    {
        try {
            Log::info("AzuraCastUploadService: Uploading file {$filename}");

                return [
                    'success' => false,
                    'error' => 'File not found: ' . $filePath,
                    'message' => 'File not found'
                ];
            }

            $response = Http::timeout(60)->withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->attach('file', file_get_contents($filePath), $filename)
              ->post($this->baseUrl . '/api/station/' . $this->stationId . '/files');

            if ($response->successful()) {
                Log::info("AzuraCastUploadService: File {$filename} uploaded successfully");
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'File uploaded successfully to AzuraCast'
                ];
            } else {
                Log::error("AzuraCastUploadService: Failed to upload file {$filename}. Status: {$response->status()}");
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'message' => 'Failed to upload file to AzuraCast'
                ];
            }
        } catch (\Exception $e) {
            Log::error("AzuraCastUploadService: Exception uploading file {$filename}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception during upload to AzuraCast'
            ];
        }
    }

    public function addMediaToPlaylist(int $playlistId, int $mediaId): array
    {
        try {
            $response = Http::timeout(30)->withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->post($this->baseUrl . '/api/station/' . $this->stationId . '/playlist/' . $playlistId . '/media', [
                'media_id' => $mediaId
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Media added to playlist successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'message' => 'Failed to add media to playlist'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception during media addition'
            ];
        }
    }
}
