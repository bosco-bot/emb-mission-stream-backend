<?php
namespace App\Services;
use Exception;
use Illuminate\Support\Facades\Http;

class AzuraCastService {
    private $baseUrl, $apiKey, $stationId;

    public function __construct() {
        $this->baseUrl = env("AZURACAST_BASE_URL");
        $this->apiKey = env("AZURACAST_API_KEY");
        $this->stationId = env("AZURACAST_STATION_ID", 1);
    }

    public function createPlaylist($name, $opts = []) {
        $data = array_merge(["name" => $name, "is_enabled" => true, "weight" => 5, "type" => "default", "order" => "sequential", "include_in_automation" => true], $opts);
        return $this->callApi("POST", $this->baseUrl."/api/station/".$this->stationId."/playlists", $data);
    }

    public function updatePlaylist($id, $name, $opts = []) {
        $data = array_merge(["name" => $name, "is_enabled" => true, "weight" => 5, "order" => "sequential", "include_in_automation" => true], $opts);
        return $this->callApi("PUT", $this->baseUrl."/api/station/".$this->stationId."/playlists/".$id, $data);
    }

    public function getPlaylists() {
        return $this->callApi("GET", $this->baseUrl."/api/station/".$this->stationId."/playlists");
    }

    public function getMediaFiles() {
        return $this->callApi("GET", $this->baseUrl."/api/station/".$this->stationId."/files");
    }

    public function findMediaByFilename($name) {
        foreach ($this->getMediaFiles() as $f) if (isset($f["path"]) && basename($f["path"]) === $name) return $f;
        return null;
    }

    public function copyMediaToDisk($localPath, $filename) {
        if (!file_exists($localPath)) throw new Exception("Fichier non trouvé");
        $dest = "/var/lib/docker/volumes/azuracast_station_data/_data/radio_emb_mission/media/".$filename;
        if (!copy($localPath, $dest)) throw new Exception("Copie échouée");
        chmod($dest, 0644);
        return $dest;
    }

    public function triggerMediaScan() {
        return $this->callApi("POST", $this->baseUrl."/api/station/".$this->stationId."/backend/restart");
    }

    public function reshufflePlaylist($playlistId) {
        return $this->callApi("POST", $this->baseUrl."/api/station/".$this->stationId."/playlist/".$playlistId."/reshuffle");
    }

    public function restartBackend() {
        return $this->callApi("POST", $this->baseUrl."/api/station/".$this->stationId."/backend/restart");
    }

    public function startBackend() {
        return $this->callApi("POST", $this->baseUrl."/api/station/".$this->stationId."/backend/start");
    }

    public function stopBackend() {
        return $this->callApi("POST", $this->baseUrl."/api/station/".$this->stationId."/backend/stop");
    }

    /**
     * Redémarre le service stations via docker-compose
     * Plus complet que restartBackend() - redémarre tout le conteneur
     * Force l'arrêt complet de la lecture en cours
     * 
     * @param bool $autoStartBackend Si true, démarre automatiquement le backend après le redémarrage
     * @return array
     */
    public function restartStationsContainer($autoStartBackend = true) {
        $composePath = env('AZURACAST_COMPOSE_PATH', '/var/azuracast');
        $serviceName = env('AZURACAST_SERVICE_NAME', 'web'); // Service par défaut: 'web'
        
        // Essayer d'abord docker compose (v2), puis docker-compose (v1) en fallback
        $command = "cd {$composePath} && docker compose restart {$serviceName} 2>&1";
        
        exec($command, $output, $returnCode);
        
        // Si docker compose échoue, essayer docker-compose (ancienne syntaxe)
        if ($returnCode !== 0) {
            $command = "cd {$composePath} && docker-compose restart {$serviceName} 2>&1";
            exec($command, $output, $returnCode);
        }
        
        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            throw new Exception("Erreur lors du redémarrage du conteneur {$serviceName}: {$errorMessage}");
        }
        
        // Attendre que le conteneur soit prêt (max 30 secondes)
        $maxWait = 30;
        $waitTime = 0;
        $apiReady = false;
        
        while ($waitTime < $maxWait && !$apiReady) {
            sleep(2);
            $waitTime += 2;
            
            // Vérifier si l'API est disponible en testant l'endpoint nowplaying
            try {
                $testResponse = Http::timeout(3)->withHeaders([
                    'X-API-Key' => $this->apiKey
                ])->get("{$this->baseUrl}/api/nowplaying/{$this->stationId}");
                
                if ($testResponse->successful()) {
                    $apiReady = true;
                }
            } catch (\Exception $e) {
                // API pas encore prête, continuer à attendre
            }
        }
        
        // Si autoStartBackend est activé, démarrer le backend automatiquement
        if ($autoStartBackend && $apiReady) {
            try {
                // Attendre encore un peu pour que tout soit bien initialisé
                sleep(2);
                $this->startBackend();
            } catch (\Exception $e) {
                // Non bloquant, on log juste l'erreur
                \Log::warning("Impossible de démarrer automatiquement le backend après redémarrage Docker: " . $e->getMessage());
            }
        }
        
        return [
            'success' => true,
            'output' => implode("\n", $output),
            'message' => "Conteneur {$serviceName} redémarré avec succès" . ($autoStartBackend && $apiReady ? " et backend démarré" : ""),
            'api_ready' => $apiReady,
            'wait_time' => $waitTime
        ];
    }

    public function getMediaIdByFilename($filename) {
        // Utiliser l'API au lieu de SQL direct
        $files = $this->getMediaFiles();
        
        foreach ($files as $file) {
            if (isset($file['path']) && basename($file['path']) === $filename) {
                return $file['id'] ?? null;
            }
        }
        
        return null;
    }

    public function addMediaToPlaylistSQL($playlistId, $mediaId) {
        $cmd = "docker exec azuracast mariadb -S /run/mysqld/mysqld.sock -u azuracast -prrkhcxR4MJeZ azuracast -e \"INSERT INTO station_playlist_media (playlist_id, media_id, weight, last_played, is_queued) VALUES (".$playlistId.", ".$mediaId.", 5, 0, 0) ON DUPLICATE KEY UPDATE weight = 5;\" 2>/dev/null";
        exec($cmd, $output, $code);
        if ($code !== 0) throw new Exception("Erreur SQL");
        return true;
    }

    private function callApi($method, $url, $data = null) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ["X-API-Key: ".$this->apiKey, "Content-Type: application/json"]]);
        if ($method === "POST") { curl_setopt($ch, CURLOPT_POST, true); if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
        elseif ($method === "PUT") { curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch) || $code >= 300) throw new Exception("API error: ".$res);
        curl_close($ch);
        return json_decode($res, true);
    }
}
