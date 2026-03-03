<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AzuraCastApiService
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

    public function uploadFile(string $filePath, string $filename, array $metadata = []): array
    {
        try {
            Log::info("AzuraCastApiService: Uploading file {$filename}");

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->attach('file', file_get_contents($filePath), $filename)
              ->post($this->baseUrl . '/api/station/' . $this->stationId . '/files');

            if ($response->successful()) {
                Log::info("AzuraCastApiService: File {$filename} uploaded successfully");
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'File uploaded successfully'
                ];
            } else {
                Log::error("AzuraCastApiService: Failed to upload file {$filename}. Status: {$response->status()}");
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'message' => 'Failed to upload file'
                ];
            }
        } catch (\Exception $e) {
            Log::error("AzuraCastApiService: Exception uploading file {$filename}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception during upload'
            ];
        }
    }

    public function getPlaylists(): array
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->get($this->baseUrl . '/api/station/' . $this->stationId . '/playlists');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Playlists retrieved successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'message' => 'Failed to retrieve playlists'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception during playlist retrieval'
            ];
        }
    }

    public function createPlaylist(string $name, array $mediaIds = [], bool $loop = false, bool $shuffle = false): array
    {
        try {
            Log::info("AzuraCastApiService: Creating playlist {$name}");

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
            ])->post($this->baseUrl . '/api/station/' . $this->stationId . '/playlists', [
                'name' => $name,
                'source' => 'songs',
                'loop' => $loop,
                'shuffle' => $shuffle,
                'media' => $mediaIds
            ]);

            if ($response->successful()) {
                Log::info("AzuraCastApiService: Playlist {$name} created successfully");
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Playlist created successfully'
                ];
            } else {
                Log::error("AzuraCastApiService: Failed to create playlist {$name}. Status: {$response->status()}");
                return [
                    'success' => false,
                    'error' => $response->body(),
                    'message' => 'Failed to create playlist'
                ];
            }
        } catch (\Exception $e) {
            Log::error("AzuraCastApiService: Exception creating playlist {$name}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception during playlist creation'
            ];
        }
    }
}
