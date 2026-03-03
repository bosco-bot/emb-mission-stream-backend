<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ListenTrackingController extends Controller
{
    public function track(Request $request)
    {
        // Récupérer les informations du client
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $referer = $request->header('Referer');
        
        // Détecter le type d'appareil
        $device = $this->detectDevice($userAgent);
        
        // Géolocalisation IP (optionnel, via ip-api.com)
        $country = $this->geolocateIP($ip);
        
        // Envoyer à Google Analytics si API Secret configuré
        if (env('GA4_API_SECRET')) {
            $this->sendToGA4($device, $country, $ip);
        }
        
        // Logger l'accès
        Log::info('Listen access', [
            'ip' => $ip,
            'device' => $device,
            'country' => $country,
            'referer' => $referer
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Tracked'
        ]);
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
        // Ne pas géolocaliser les IPs locales
        if ($this->isLocalIP($ip)) {
            return 'Local';
        }
        
        try {
            $response = Http::timeout(3)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'country,countryCode'
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['country'] ?? 'Unknown';
            }
        } catch (\Exception $e) {
            // Ignorer les erreurs
        }
        
        return 'Unknown';
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
    
    private function sendToGA4($device, $country, $ip)
    {
        $measurementId = 'G-ZN07XKXPCN';
        $apiSecret = env('GA4_API_SECRET');
        $clientId = hash('sha256', 'emb-mission-' . $ip);
        
        $payload = [
            'client_id' => $clientId,
            'events' => [
                [
                    'name' => 'stream_access',
                    'params' => [
                        'stream_url' => 'https://radio.embmission.com/listen',
                        'stream_type' => 'radio',
                        'device_type' => $device,
                        'country' => $country,
                        'access_method' => 'direct'
                    ]
                ]
            ]
        ];
        
        try {
            $response = Http::timeout(5)
                ->post("https://www.google-analytics.com/mp/collect?measurement_id={$measurementId}&api_secret={$apiSecret}", $payload);
            
            if ($response->successful()) {
                Log::info('✅ Événement GA4 stream_access envoyé', [
                    'ip' => $ip,
                    'country' => $country,
                    'device' => $device
                ]);
            } else {
                Log::warning('⚠️ Échec envoi événement GA4 stream_access', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('❌ Erreur tracking GA4 /listen: ' . $e->getMessage());
        }
    }
}

