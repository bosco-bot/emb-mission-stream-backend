<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\LiveStatusController;
use App\Http\Controllers\Api\RadioStreamController;
use App\Http\Controllers\Api\RadioSourceController;
use App\Http\Controllers\ListenTrackingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Route de test simple

// Route de test avec paramètres
Route::get('/hello/{name}', function ($name) {
    return response()->json([
        'success' => true,
        'message' => "Bonjour $name!",
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Route de test POST
Route::post('/echo', function (Request $request) {
    return response()->json([
        'success' => true,
        'message' => 'Données reçues',
        'data' => $request->all(),
        'timestamp' => now()->toDateTimeString()
    ]);
});

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Routes d'authentification (publiques)
// Route pour récupérer l'utilisateur actuel (compatible Flutter)
Route::prefix('auth')->group(function () {
    Route::get('/me', function (Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié',
                'data' => null
            ], 401);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ]
        ]);
    })->middleware('auth:sanctum');
});

Route::controller(RegisterController::class)->group(function() {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
});

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [RegisterController::class, 'logout']);
});

// Routes pour les sources radio
Route::prefix('radio')->group(function () {
    Route::get("/broadcast-info", [App\Http\Controllers\Api\RadioStreamController::class, "getBroadcastInfo"]);
});

// Routes pour le streaming radio
Route::prefix('radio')->group(function () {
    Route::get('/stream/current', [RadioStreamController::class, 'getCurrentTrack']);
    Route::get('/track/current', [RadioStreamController::class, 'getCurrentTrack']);
    Route::get('/stream/history', [RadioStreamController::class, 'getSongHistory']);
    Route::get('/stream/upcoming', [RadioStreamController::class, 'getSongHistory']);
});

// Flux SSE statut live/VOD
Route::get('/webtv/live/status/stream', [LiveStatusController::class, 'stream']);

// ✅ NOUVEAU : Flux SSE avec transition gérée par le backend (pour test)
use App\Http\Controllers\Api\LiveStatusControllerNew;
Route::get('/webtv/live/status/stream-new', [LiveStatusControllerNew::class, 'stream']);

// Routes pour la gestion des fichiers média
Route::prefix('media')->group(function () {
    // Routes publiques (pour l'interface de médiathèque)
    Route::get('/files', [App\Http\Controllers\Api\MediaController::class, 'index']);
    Route::get('/files/importing', [App\Http\Controllers\Api\MediaController::class, 'getImportingFiles']);
    Route::get('/files/errors', [App\Http\Controllers\Api\MediaController::class, 'getErrorFiles']);
    Route::get('/files/completed', [App\Http\Controllers\Api\MediaController::class, 'getCompletedFiles']);
    Route::get("/files/audio", [App\Http\Controllers\Api\MediaController::class, 'index']);
    Route::get("/files/video", [App\Http\Controllers\Api\MediaController::class, 'index']);
    Route::get("/files/image", [App\Http\Controllers\Api\MediaController::class, 'index']);
    Route::get('/files/conversion-status', [App\Http\Controllers\Api\MediaController::class, 'getConversionStatus']);
    Route::get('/files/{mediaFile}', [App\Http\Controllers\Api\MediaController::class, 'show']);
    
    // Actions sur les fichiers
    Route::delete('/files/{mediaFile}/cancel', [App\Http\Controllers\Api\MediaController::class, 'cancelImport']);
    Route::post('/files/{mediaFile}/retry', [App\Http\Controllers\Api\MediaController::class, 'retryImport']);
    Route::delete('/files/{mediaFile}', [App\Http\Controllers\Api\MediaController::class, 'destroy']);
    Route::put('/files/update/{mediaFile}', [App\Http\Controllers\Api\MediaController::class, 'updateFlutter']);
    Route::put('/files/{mediaFile}', [App\Http\Controllers\Api\MediaController::class, 'update']);
    
    // Upload de fichiers
    Route::post('/upload-session', [App\Http\Controllers\Api\MediaController::class, 'createUploadSession']);
    Route::post('/upload', [App\Http\Controllers\Api\MediaController::class, 'uploadFile']);
    Route::post('/upload-multiple', [App\Http\Controllers\Api\MediaController::class, 'uploadMultipleFiles']);
    // Upload par chunks (pour fichiers volumineux)
    Route::post('/upload-chunk', [App\Http\Controllers\Api\MediaController::class, 'uploadChunk']);
    Route::post('/finalize-chunk-upload', [App\Http\Controllers\Api\MediaController::class, 'finalizeChunkUpload']);
    Route::post('/cancel-chunk-upload', [App\Http\Controllers\Api\MediaController::class, 'cancelChunkUpload']);

});


// Routes pour les playlists
Route::apiResource('playlists', App\Http\Controllers\Api\PlaylistController::class);
Route::post('playlists/{playlist}/sync-azuracast', [App\Http\Controllers\Api\PlaylistController::class, 'syncToAzuraCast']);
Route::post('playlists/{playlist}/update-m3u-restart', [App\Http\Controllers\Api\PlaylistController::class, 'updateM3UAndRestart']);
Route::get('playlists/{playlist}/sync-status', [App\Http\Controllers\Api\PlaylistController::class, 'syncStatus']);
Route::post('playlists/create-missing-in-azuracast', [App\Http\Controllers\Api\PlaylistController::class, 'createMissingPlaylistsInAzuraCast']);
Route::post('playlists/clean-azuracast', [App\Http\Controllers\Api\PlaylistController::class, 'cleanAzuraCast']);

// Route pour supprimer toutes les playlists

// Route pour supprimer toutes les playlists

// Route pour supprimer toutes les playlists
Route::delete('playlists/delete-all', [App\Http\Controllers\Api\PlaylistController::class, 'deleteAll']);

// Route POST alternative pour supprimer toutes les playlists (compatible Nginx)
Route::post('playlists/delete-all', [App\Http\Controllers\Api\PlaylistController::class, 'deleteAll']);

// Route pour supprimer un élément d'une playlist
Route::delete('playlists/{playlist}/items/{item}', [App\Http\Controllers\Api\PlaylistItemController::class, 'removeItem']);

// Route POST alternative pour supprimer un élément (compatible Nginx)
Route::post('playlists/{playlist}/items/{item}/delete', [App\Http\Controllers\Api\PlaylistItemController::class, 'removeItem']);

// Synchronisation AzuraCast
Route::post('/sync/azuracast', [App\Http\Controllers\Api\SyncController::class, 'syncAzuracast']);

// Routes AzuraCast Playlists
Route::get('/azuracast/playlists', [App\Http\Controllers\Api\AzuraCastController::class, 'getPlaylists']);
Route::post('/azuracast/playlists', [App\Http\Controllers\Api\AzuraCastController::class, 'createPlaylist']);
Route::post('/azuracast/playlists/{playlist}/media', [App\Http\Controllers\Api\AzuraCastController::class, 'addMediaToPlaylist']);

// Route de test

// Routes pour les items de playlist (supprimé - doublon avec lignes 176-178)

// Routes pour l'upload vers AzuraCast
Route::post('/azuracast/upload', [App\Http\Controllers\Api\AzuraCastUploadController::class, 'uploadFile']);
Route::post('/azuracast/add-to-playlist', [App\Http\Controllers\Api\AzuraCastUploadController::class, 'addToPlaylist']);

// Route pour les commandes de synchronisation
Route::get('/sync/commands', [App\Http\Controllers\Api\SyncController::class, 'getSyncCommands']);

// Routes de synchronisation AzuraCast
Route::post('/playlists/{playlist}/sync', [App\Http\Controllers\Api\PlaylistSyncController::class, 'syncPlaylist']);
Route::post('/azuracast/copy-file', [App\Http\Controllers\Api\PlaylistSyncController::class, 'copyFile']);
Route::post('/azuracast/scan', [App\Http\Controllers\Api\PlaylistSyncController::class, 'scanMedia']);
Route::get('/azuracast/files', [App\Http\Controllers\Api\PlaylistSyncController::class, 'getFiles']);

// Routes pour les items de playlist avec auto-sync
Route::post('/playlists/{playlist}/items', [App\Http\Controllers\Api\PlaylistItemController::class, 'addItem']);
Route::delete('/playlists/{playlist}/items/{item}', [App\Http\Controllers\Api\PlaylistItemController::class, 'removeItem']);
Route::put('/playlists/{playlist}/items/order', [App\Http\Controllers\Api\PlaylistItemController::class, 'updateOrder']);

// Routes pour les paramètres de streaming
Route::prefix('radio')->group(function () {
    Route::get('/streaming-settings', [App\Http\Controllers\Api\RadioStreamingController::class, 'getStreamingSettings']);
    Route::post('/streaming-settings/generate-key', [App\Http\Controllers\Api\RadioStreamingController::class, 'generateNewKey']);
    Route::get('/streaming-settings/test-connection', [App\Http\Controllers\Api\RadioStreamingController::class, 'testConnection']);
});

// Routes WebRadio (Proxy vers AzuraCast)
Route::prefix('webradio')->group(function () {
    Route::get('/current-stream', [App\Http\Controllers\Api\WebRadioController::class, 'getCurrentStream']);
    Route::get('/track-history', [App\Http\Controllers\Api\WebRadioController::class, 'getTrackHistory']);
    Route::post('/control', [App\Http\Controllers\Api\WebRadioController::class, 'controlBroadcast']);
});


// Routes pour la diffusion live Mixxx
Route::prefix('mixxx')->group(function () {
    Route::post('/start', [App\Http\Controllers\Api\MixxxController::class, 'start']);
    Route::post('/stop', [App\Http\Controllers\Api\MixxxController::class, 'stop']);
    Route::get('/status', [App\Http\Controllers\Api\MixxxController::class, 'status']);
    Route::put('/settings', [App\Http\Controllers\Api\MixxxController::class, 'updateSettings']);
});

// ========================================
// ROUTES WEBTV (ANT MEDIA SERVER)
// ========================================

Route::prefix("webtv")->group(function () {
    Route::get("/current-stream", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getCurrentPlaybackUrl"]);
    Route::get("/streams", [App\Http\Controllers\Api\WebTVController::class, "getStreams"]);
    Route::get("/recent-broadcasts", [App\Http\Controllers\Api\WebTVController::class, "getRecentBroadcasts"]);
    Route::get("/check-connection", [App\Http\Controllers\Api\WebTVController::class, "checkConnection"]);
    Route::get("/stream-status", [App\Http\Controllers\Api\WebTVController::class, "getStreamStatus"]);
    Route::get("/embed-code", [App\Http\Controllers\Api\WebTVController::class, "getEmbedCode"]);
});


// ========================================
// ROUTES WEBTV PLAYLIST
// ========================================

Route::prefix("webtv-playlists")->group(function () {
    // Lister toutes les playlists WebTV
    Route::get("/", [App\Http\Controllers\Api\WebTVPlaylistController::class, "index"]);
    
    // Créer une nouvelle playlist WebTV
    Route::post("/", [App\Http\Controllers\Api\WebTVPlaylistController::class, "store"]);
    
    // Afficher une playlist WebTV spécifique
    Route::get("/{webTVPlaylist}", [App\Http\Controllers\Api\WebTVPlaylistController::class, "show"]);
    
    // Mettre à jour une playlist WebTV
    Route::put("/{webTVPlaylist}", [App\Http\Controllers\Api\WebTVPlaylistController::class, "update"]);
    
    // Supprimer une playlist WebTV
    Route::delete("/{webTVPlaylist}", [App\Http\Controllers\Api\WebTVPlaylistController::class, "destroy"]);
    
    // Activer/Désactiver une playlist WebTV
    Route::patch("/{webTVPlaylist}/toggle-status", [App\Http\Controllers\Api\WebTVPlaylistController::class, "toggleStatus"]);
    
    // Obtenir les statistiques d'une playlist WebTV
    Route::get("/{webTVPlaylist}/stats", [App\Http\Controllers\Api\WebTVPlaylistController::class, "getStats"]);

    Route::post("/{webTVPlaylist}/sync", [App\Http\Controllers\Api\WebTVPlaylistController::class, "syncWithAntMedia"]);
});


// ========================================
// ROUTES WEBTV PLAYLIST ITEMS
// ========================================

Route::prefix("webtv-playlists")->group(function () {
    // Lister tous les items d'une playlist WebTV
    Route::get("/{webTVPlaylist}/items", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "index"]);
    
    // Ajouter un nouvel item à une playlist WebTV
    Route::post("/{webTVPlaylist}/items", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "store"]);
    
    // Mettre à jour l'ordre des items d'une playlist WebTV
    Route::put("/{webTVPlaylist}/items/order", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "updateOrder"]);
    
    // Afficher un item spécifique d'une playlist WebTV
    Route::get("/{webTVPlaylist}/items/{webTVPlaylistItem}", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "show"]);
    
    // Mettre à jour un item d'une playlist WebTV
    Route::put("/{webTVPlaylist}/items/{webTVPlaylistItem}", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "update"]);
    
    // Supprimer un item d'une playlist WebTV
    Route::delete("/{webTVPlaylist}/items/{webTVPlaylistItem}", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "destroy"]);
    
    // Marquer un item comme synchronisé avec Ant Media
    Route::patch("/{webTVPlaylist}/items/{webTVPlaylistItem}/mark-synced", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "markAsSynced"]);
    
    // Marquer un item comme erreur de synchronisation
    Route::patch("/{webTVPlaylist}/items/{webTVPlaylistItem}/mark-sync-error", [App\Http\Controllers\Api\WebTVPlaylistItemController::class, "markAsSyncError"]);
});


// Routes pour la playlist automatique WebTV
Route::prefix("webtv-auto-playlist")->group(function () {
    Route::post("/{webTVPlaylist}/start", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "startAutoPlaylist"]);
    Route::get("/monitor", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "monitorAutoSwitch"]);
    
    Route::get("/check-live-only", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "checkLiveStatusOnly"]);
    
    Route::get("/force-refresh", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "forceRefreshStatus"]);
    
    Route::post("/cleanup-connections", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "cleanupGhostConnections"]);
    
    Route::get("/detailed-stats", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getDetailedStats"]);
    
    Route::get("/status", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getAutoPlaylistStatus"]);
    Route::get("/current-url", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getCurrentPlaybackUrl"]);
    Route::post("/stop", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "stopAutoPlaylist"]);
    Route::post("/resume", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "resumeAutoPlaylist"]);
    Route::get("/obs-params", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getOBSConnectionParams"]);
    Route::get("/public-stream-url", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getPublicStreamUrl"]);
    Route::get("/next-vod", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "getNextVodItem"]);
    
    Route::post("/refresh-cache", [App\Http\Controllers\Api\WebTVAutoPlaylistController::class, "refreshCache"]);
});


// ========================================
// ROUTES WEBTV STATS
// ========================================

Route::prefix("webtv")->group(function () {
    // ✅ NOUVELLE API UNIFIÉE - Toutes les stats en une seule requête
    Route::get("/stats/all", [App\Http\Controllers\Api\UnifiedStatsController::class, "getAllStats"]);
    Route::get("/stats/debug", [App\Http\Controllers\Api\UnifiedStatsController::class, "debugStats"]);
    Route::post("/stats/clear-cache", [App\Http\Controllers\Api\UnifiedStatsController::class, "clearCache"]);
    
    // APIs existantes (pour compatibilité)
    Route::get("/stats/live-audience", [App\Http\Controllers\Api\WebTVStatsController::class, "getLiveAudience"]);
    Route::get("/stats/current-duration", [App\Http\Controllers\Api\WebTVStatsController::class, "getCurrentStreamDuration"]);
    Route::get("/stats/total-views", [App\Http\Controllers\Api\WebTVStatsController::class, "getTotalViews"]);
    Route::get("/stats/broadcast-duration", [App\Http\Controllers\Api\WebTVStatsController::class, "getBroadcastDuration"]);
    Route::get("/stats/engagement", [App\Http\Controllers\Api\WebTVStatsController::class, "getEngagement"]);
});
// ========================================
// ROUTES MEDIA LIBRARY
// ========================================

Route::prefix("media")->group(function () {
    Route::get("/library/stats", [App\Http\Controllers\Api\MediaLibraryController::class, "getStats"]);
});

// ========================================
// ROUTES TRACKING ANALYTICS
// ========================================

Route::post('/track-listen', [ListenTrackingController::class, 'track']);
