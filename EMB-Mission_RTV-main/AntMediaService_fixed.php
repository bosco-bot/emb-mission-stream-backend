<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AntMediaService
{
    private string $baseUrl;
    private string $appId;
    private ?string $username;
    private ?string $password;
    private string $publicUrl; // URL publique pour les clients

    public function __construct()
    {
        // Base URL should be just the server address without /rest/v2
        $this->baseUrl = config('app.ant_media_server_url', 'http://localhost:5080');
        $this->appId = config('app.ant_media_server_app_id', 'LiveApp');
        $this->username = config('app.ant_media_server_username');
        $this->password = config('app.ant_media_server_password');
        
        // URL publique pour les clients (HTTPS)
        $this->publicUrl = 'https://tv.embmission.com/webtv-live';
    }

    public function checkConnection()
    {
        try {
            $response = $this->makeRequest('GET', '/broadcasts/list/0/1');
            return $response !== null;
        } catch (\Exception $e) {
            Log::error('AntMediaService: Erreur de connexion: ' . $e->getMessage());
            return false;
        }
    }

    public function getBroadcasts($offset = 0, $size = 50)
    {
        try {
            $response = $this->makeRequest('GET', '/broadcasts/list/' . $offset . '/' . $size);
            return $response;
        } catch (\Exception $e) {
            Log::error('AntMediaService: Erreur: ' . $e->getMessage());
            return null;
        }
    }

    public function getMainWebTVStream()
    {
        $broadcasts = $this->getBroadcasts(0, 1);
        
        if ($broadcasts && is_array($broadcasts) && !empty($broadcasts)) {
            return $broadcasts[0];
        }
        
        return null;
    }

    public function getStreamStatus(string $streamId)
    {
        try {
            $response = $this->makeRequest('GET', '/broadcasts/' . $streamId);
            
            if (!$response) {
                return 'offline';
            }
            
            $status = $response['status'] ?? 'offline';
            return $status === 'broadcasting' ? 'live' : 'offline';
        } catch (\Exception $e) {
            Log::error('AntMediaService: Erreur statut stream ' . $streamId . ': ' . $e->getMessage());
            return 'offline';
        }
    }

    public function getPlaybackUrl(string $streamId): string
    {
        // Retourne l'URL publique HTTPS pour les clients
        return "{$this->publicUrl}/streams/{$streamId}.m3u8";
    }

    public function getEmbedUrl(string $streamId): string
    {
        // Retourne l'URL publique HTTPS pour le player
        return "{$this->publicUrl}/play.html?name={$streamId}";
    }

    public function getWebRTCUrl(string $streamId): string
    {
        // WebRTC utilise wss:// pour HTTPS
        return "wss://tv.embmission.com/webtv-live/{$streamId}";
    }

    public function getThumbnailUrl(string $streamId): string
    {
        // URL de la miniature
        return "{$this->publicUrl}/previews/{$streamId}.png";
    }

    private function makeRequest(string $method, string $endpoint, array $data = [])
    {
        try {
            // Build URL: http://localhost:5080/LiveApp/rest/v2/broadcasts/list/0/10
            $url = $this->baseUrl . '/' . $this->appId . '/rest/v2' . $endpoint;
            
            $httpClient = Http::timeout(10);
            
            if ($this->username && $this->password) {
                $httpClient = $httpClient->withBasicAuth($this->username, $this->password);
            }

            if ($method === 'GET') {
                $response = $httpClient->get($url, $data);
            } else {
                $response = $httpClient->post($url, $data);
            }

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('AntMediaService: Erreur HTTP ' . $response->status() . ' pour ' . $url);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('AntMediaService: Erreur requête ' . $method . ' ' . $endpoint . ': ' . $e->getMessage());
            return null;
        }
    }
}











