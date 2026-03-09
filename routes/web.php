<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Controller_login_register;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route par défaut - Redirection vers la page de connexion
Route::get('/', function () {
    return redirect()->route('login');
});

// Routes d'authentification
Route::group(['prefix' => 'auth', 'as' => ''], function () {
    
    // Routes de connexion
    Route::get('/login', [Controller_login_register::class, 'showLoginForm'])->name('login');
    Route::post('/login', [Controller_login_register::class, 'login'])->name('login.post');
    
    // Routes d'inscription
    Route::get('/register', [Controller_login_register::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [Controller_login_register::class, 'register'])->name('register.post');
    
    // Route de déconnexion
    Route::post('/logout', [Controller_login_register::class, 'logout'])->name('logout');
    
    // Routes AJAX pour validation en temps réel
    Route::post('/check-email', [Controller_login_register::class, 'checkEmail'])->name('check.email');
    Route::post('/check-password-strength', [Controller_login_register::class, 'checkPasswordStrength'])->name('check.password');
});

// Routes protégées (nécessitent une authentification)
Route::middleware(['auth'])->group(function () {
    
    // Dashboard principal
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    // Autres routes protégées peuvent être ajoutées ici
    // Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    // Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
});
Route::get("/watch", function () { return view("watch"); });

//Route::get("/watch", function () { return view("watch-new"); });

Route::get("/watch-new", function () { return view("watch-new"); }); // ✅ Test transition backend
Route::get("/player", function () { return view("player"); });
// Embed YouTube pour chaînes partenaires : /embed ou /embed/VIDEO_ID ou /embed?id=VIDEO_ID
Route::get('/embed/{videoId?}', \App\Http\Controllers\EmbedController::class)->name('embed.youtube');

// Route pour générer le fichier M3U pour WebRadio
Route::get('/playlist.m3u', function () {
    // ✅ Tracking GA4 pour lecteurs externes
    $clientIp = request()->ip();
    $userAgent = request()->header('User-Agent', '');
    $ga4MeasurementId = env('GA4_MEASUREMENT_ID_RADIO', 'G-ZN07XKXPCN');
    $ga4ApiSecret = env('GA4_API_SECRET');
    
    if ($ga4ApiSecret) {
        try {
            // Géolocalisation IP
            $country = 'Unknown';
            $response = \Illuminate\Support\Facades\Http::timeout(2)->get("http://ip-api.com/json/{$clientIp}?fields=country");
            if ($response->successful()) {
                $country = $response->json('country', 'Unknown');
            }
            
            // Détection type d'appareil
            $deviceType = 'Unknown';
            if (\Illuminate\Support\Str::contains($userAgent, ['Mobile', 'Android', 'iPhone', 'iPad'])) {
                $deviceType = 'Mobile';
            } elseif (\Illuminate\Support\Str::contains($userAgent, ['Windows', 'Macintosh', 'Linux'])) {
                $deviceType = 'Desktop';
            } else {
                $deviceType = 'Other';
            }
            
            // Envoyer à GA4 en arrière-plan (non-bloquant)
            $ga4Response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->timeout(1)
                ->post("https://www.google-analytics.com/mp/collect?measurement_id={$ga4MeasurementId}&api_secret={$ga4ApiSecret}", [
                    'client_id' => hash('sha256', $clientIp . $userAgent),
                    'events' => [
                        [
                            'name' => 'm3u_playlist_download',
                            'params' => [
                                'engagement_time_msec' => '1',
                                'ip_address' => $clientIp,
                                'user_agent' => $userAgent,
                                'country' => $country,
                                'device_type' => $deviceType,
                                'playlist_url' => 'https://radio.embmission.com/playlist.m3u',
                                'event_source' => 'external_player'
                            ],
                        ],
                    ],
                ]);
            
            if ($ga4Response->successful()) {
                \Illuminate\Support\Facades\Log::info('✅ Événement GA4 m3u_playlist_download envoyé', [
                    'ip' => $clientIp,
                    'country' => $country,
                    'device' => $deviceType
                ]);
            } else {
                \Illuminate\Support\Facades\Log::warning('⚠️ Échec envoi événement GA4 m3u_playlist_download', [
                    'status' => $ga4Response->status(),
                    'body' => $ga4Response->body()
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ Erreur tracking GA4 playlist.m3u: ' . $e->getMessage());
        }
    }
    
    $streamUrl = 'https://radio.embmission.com/listen';
    
    $m3uContent = "#EXTM3U\n";
    $m3uContent .= "#EXTINF:-1,EMB Mission - WebRadio\n";
    $m3uContent .= $streamUrl . "\n";
    
    return response($m3uContent, 200)
        ->header('Content-Type', 'audio/x-mpegurl')
        ->header('Content-Disposition', 'attachment; filename=radio_emb_mission.m3u');
});


// 🎯 ENDPOINT UNIFORME HLS - Stream qui fonctionne toujours (Live + VoD)
Route::get("/api/stream/unified.m3u8", [App\Http\Controllers\Api\UnifiedStreamController::class, "getUnifiedHLS"]);
Route::options("/api/stream/unified.m3u8", [App\Http\Controllers\Api\UnifiedStreamController::class, "options"]);

// 📊 ROUTES MONITORING (Accessibles publiquement comme /watch)
Route::prefix('system-monitoring')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\MonitoringController::class, 'index'])->name('system-monitoring.index');
    Route::get('/status', [App\Http\Controllers\Admin\MonitoringController::class, 'getStatus'])->name('system-monitoring.status');
    Route::post('/restart-service', [App\Http\Controllers\Admin\MonitoringController::class, 'restartService'])->name('system-monitoring.restart-service');
    Route::post('/stop-service', [App\Http\Controllers\Admin\MonitoringController::class, 'stopService'])->name('system-monitoring.stop-service');
    Route::post('/retry-job', [App\Http\Controllers\Admin\MonitoringController::class, 'retryJob'])->name('system-monitoring.retry-job');
    Route::post('/retry-all-jobs', [App\Http\Controllers\Admin\MonitoringController::class, 'retryAllFailedJobs'])->name('system-monitoring.retry-all-jobs');
});
