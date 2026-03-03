<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class AnalyticsListeningTracker extends Command
{
    protected $signature = 'analytics:track-listeners {--hours=24}';
    protected $description = 'Track listeners accessing /listen via Google Analytics Measurement Protocol';

    public function handle()
    {
        $this->info('🎵 Démarrage du tracking des auditeurs...');
        
        $hours = $this->option('hours');
        $nginxLogPath = '/var/log/nginx/listen_access.log';
        
        if (!File::exists($nginxLogPath)) {
            $this->error("Log file not found: {$nginxLogPath}");
            return 1;
        }
        
        // Lire les dernières lignes du log
        $lines = $this->readLastLogLines($nginxLogPath, $hours);
        
        $listeners = $this->extractListeners($lines);
        
        if (empty($listeners)) {
            $this->info('Aucun nouveau auditeur détecté.');
            return 0;
        }
        
        $this->info("📊 Détection de " . count($listeners) . " connexions à /listen");
        
        // Envoyer les données à Google Analytics
        $tracked = $this->sendToGoogleAnalytics($listeners);
        
        if ($tracked) {
            $this->info('✅ Données envoyées à Google Analytics avec succès!');
        } else {
            $this->error('❌ Erreur lors de l\'envoi à Google Analytics');
        }
        
        return 0;
    }
    
    private function readLastLogLines($logPath, $hours)
    {
        // Lire le fichier de log
        $file = new \SplFileObject($logPath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        // Lire les dernières lignes
        $linesToRead = 1000; // Lire au maximum 1000 dernières lignes
        $startLine = max(0, $totalLines - $linesToRead);
        
        $lines = [];
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = $file->current();
            if (!empty(trim($line))) {
                $lines[] = $line;
            }
            $file->next();
        }
        
        return $lines;
    }
    
    private function extractListeners($lines)
    {
        $listeners = [];
        $seenIPs = []; // Pour éviter les doublons
        $hours = $this->option('hours');
        $cutoffTime = time() - ($hours * 3600); // Timestamp limite
        
        foreach ($lines as $line) {
            // Parser la ligne de log Nginx
            // Format standard: IP - - [DATE] "METHOD /listen ..." STATUS SIZE "REFERER" "USER-AGENT"
            
            if (!preg_match('/GET \/listen/', $line)) {
                continue;
            }
            
            // Extraire l'IP
            if (!preg_match('/^([\d\.]+)/', $line, $ipMatch)) {
                continue;
            }
            
            $ip = $ipMatch[1];
            
            // Éviter les IPs locales et les doublons
            if ($this->isLocalIP($ip) || isset($seenIPs[$ip])) {
                continue;
            }
            
            // Extraire le User-Agent (dernier champ entre guillemets)
            if (preg_match('/"([^"]*)"$/', $line, $uaMatch)) {
                $userAgent = $uaMatch[1] ?? '';
            } else {
                $userAgent = '';
            }
            
            // Extraire la date/heure
            preg_match('/\[([^\]]+)\]/', $line, $dateMatch);
            $timestamp = isset($dateMatch[1]) ? strtotime($dateMatch[1]) : time();
            
            // ✅ Filtrer par heures
            if ($timestamp < $cutoffTime) {
                continue;
            }
            
            // Extraire le status code (format: "GET /listen HTTP/2.0" 200)
            if (preg_match('/HTTP\/[\d.]+"\s+(\d+)/', $line, $statusMatch)) {
                $status = (int)$statusMatch[1];
            } else {
                $status = 0;
            }
            
            // Ne garder que les connexions réussies
            if ($status !== 200) {
                continue;
            }
            
            $seenIPs[$ip] = true;
            
            $listeners[] = [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'timestamp' => $timestamp,
                'device' => $this->detectDevice($userAgent),
                'country' => $this->geolocateIP($ip)
            ];
        }
        
        return $listeners;
    }
    
    private function isLocalIP($ip)
    {
        $localIPs = ['127.0.0.1', 'localhost', '::1', '192.168.', '10.', '172.'];
        foreach ($localIPs as $local) {
            if (strpos($ip, $local) === 0) {
                return true;
            }
        }
        return false;
    }
    
    private function detectDevice($userAgent)
    {
        $ua = strtolower($userAgent);
        
        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) {
            return 'Mobile';
        }
        
        if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) {
            return 'Tablet';
        }
        
        return 'Desktop';
    }
    
    private function geolocateIP($ip)
    {
        try {
            $response = Http::timeout(5)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'country,countryCode,city'
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['country'] ?? 'Unknown';
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs de géolocalisation
        }
        
        return 'Unknown';
    }
    
    private function sendToGoogleAnalytics($listeners)
    {
        $measurementId = 'G-ZN07XKXPCN';
        $apiSecret = env('GA4_API_SECRET'); // À configurer dans .env
        
        if (empty($apiSecret)) {
            $this->warn('⚠️  GA4_API_SECRET non configuré dans .env');
            $this->info('Pour activer le tracking, ajoutez GA4_API_SECRET=votre_secret dans .env');
            return false;
        }
        
        $clientId = hash('sha256', 'emb-mission-listener');
        
        foreach ($listeners as $listener) {
            $payload = [
                'client_id' => $clientId,
                'events' => [
                    [
                        'name' => 'direct_stream_access',
                        'params' => [
                            'stream_url' => 'https://radio.embmission.com/listen',
                            'stream_type' => 'radio',
                            'device' => $listener['device'],
                            'country' => $listener['country'],
                            'method' => 'direct_access'
                        ]
                    ]
                ]
            ];
            
            try {
                $response = Http::timeout(5)
                    ->post("https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}", $payload);
                
                if ($response->successful()) {
                    \Illuminate\Support\Facades\Log::info('✅ Événement GA4 direct_stream_access envoyé via cron', [
                        'ip' => $listener['ip'],
                        'country' => $listener['country'],
                        'device' => $listener['device']
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('⚠️ Échec envoi événement GA4 direct_stream_access via cron', [
                        'ip' => $listener['ip'],
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $this->warn("Erreur pour IP {$listener['ip']}: {$response->status()}");
                }
                
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('❌ Erreur tracking GA4 direct_stream_access via cron: ' . $e->getMessage(), [
                    'ip' => $listener['ip']
                ]);
                $this->warn("Erreur pour IP {$listener['ip']}: {$e->getMessage()}");
            }
            
            // Limiter le nombre de requêtes pour éviter les quotas
            usleep(100000); // 100ms entre chaque requête
        }
        
        return true;
    }
}

