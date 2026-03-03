<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\MediaFile;
use App\Services\AzuraCastSyncService;
use App\Services\AzuraCastService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlaylistController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $playlists = Playlist::with('items.mediaFile')->latest()->get();
            
            return response()->json([
                'success' => true,
                'data' => $playlists,
                'message' => 'Playlists récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des playlists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'type' => 'nullable|string',
                'is_loop' => 'boolean',
                'is_shuffle' => 'boolean',
                'items' => 'nullable|array',
                'items.*' => 'integer|exists:media_files,id',
                'auto_sync' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                $playlist = Playlist::whereRaw('LOWER(name) = ?', [strtolower($request->name)])->first();

                $action = $playlist ? 'updated' : 'created';
                $itemsAdded = 0;
                $itemsSkipped = 0;

                if ($playlist) {
                    $playlist->update([
                        'description' => $request->description ?? $playlist->description,
                        'type' => $request->type ?? $playlist->type,
                        'is_loop' => $request->has('is_loop') ? $request->is_loop : $playlist->is_loop,
                        'is_shuffle' => $request->has('is_shuffle') ? $request->is_shuffle : $playlist->is_shuffle,
                    ]);

                    if ($request->has('items') && is_array($request->items)) {
                        // ✅ OPTIMISATION: Récupérer les items existants ET le max(order) en UNE requête
                        $existingItemsData = PlaylistItem::where('playlist_id', $playlist->id)
                            ->select('media_file_id', 'order')
                            ->get();
                        
                        // ✅ OPTIMISATION: Utiliser un tableau associatif pour recherche O(1) au lieu de O(n)
                        $existingItemsMap = $existingItemsData->pluck('order', 'media_file_id')->toArray();
                        $lastOrder = $existingItemsData->max('order') ?? 0;
                        
                        // Créer tous les nouveaux items
                        $newItems = [];
                        foreach ($request->items as $mediaFileId) {
                            // ✅ OPTIMISATION: isset() est O(1) vs in_array() qui est O(n)
                            if (!isset($existingItemsMap[$mediaFileId])) {
                                $lastOrder++;
                                $newItems[] = [
                                    'playlist_id' => $playlist->id,
                                    'media_file_id' => $mediaFileId,
                                    'order' => $lastOrder,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                                $itemsAdded++;
                                $existingItemsMap[$mediaFileId] = $lastOrder; // Pour éviter les doublons dans la même requête
                            } else {
                                $itemsSkipped++;
                            }
                        }
                        
                        // ✅ Insérer tous les nouveaux items en UNE fois (batch insert)
                        if (!empty($newItems)) {
                            PlaylistItem::insert($newItems);
                        }
                    }

                } else {
                    $playlist = Playlist::create([
                        'name' => $request->name,
                        'description' => $request->description,
                        'type' => $request->type ?? 'default',
                        'is_loop' => $request->is_loop ?? false,
                        'is_shuffle' => $request->is_shuffle ?? false,
                    ]);

                    if ($request->has('items') && is_array($request->items)) {
                        // ✅ OPTIMISATION: Créer tous les items en UNE fois (batch insert)
                        $newItems = [];
                        foreach ($request->items as $order => $mediaFileId) {
                            $newItems[] = [
                                'playlist_id' => $playlist->id,
                                'media_file_id' => $mediaFileId,
                                'order' => $order + 1,
                                'created_at' => now(),
                                'updated_at' => now()
                            ];
                        }
                        if (!empty($newItems)) {
                            PlaylistItem::insert($newItems);
                            $itemsAdded = count($newItems);
                        }
                    }
                }

                // ✅ OPTIMISATION: Recalculer les stats AVANT le commit pour éviter une requête supplémentaire
                $playlist->recalculateStats();
                
                DB::commit();

                // ✅ OPTIMISATION: Charger les items seulement si nécessaire (pour la réponse)
                $playlist->load('items.mediaFile');

                // ✅ Aucune synchronisation ni redémarrage automatique dans store()
                // La synchronisation et le redémarrage doivent être déclenchés manuellement
                // via POST /api/playlists/{playlist}/update-m3u-restart
                $syncResult = [
                    'success' => true,
                    'message' => 'Synchronisation AzuraCast non déclenchée automatiquement. Utilisez /update-m3u-restart pour synchroniser et redémarrer.'
                ];

                return response()->json([
                    'success' => true,
                    'action' => $action,
                    'data' => $playlist,
                    'items_added' => $itemsAdded,
                    'items_skipped' => $itemsSkipped,
                    'sync' => $syncResult,
                    'message' => $action === 'created' 
                        ? 'Playlist créée avec succès' 
                        : "Playlist mise à jour avec succès ($itemsAdded items ajoutés, $itemsSkipped ignorés)"
                ], $action === 'created' ? 201 : 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création/mise à jour de la playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Playlist $playlist): JsonResponse
    {
        try {
            $playlist->load('items.mediaFile');
            
            return response()->json([
                'success' => true,
                'data' => $playlist,
                'message' => 'Playlist récupérée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Playlist non trouvée',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, Playlist $playlist): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'type' => 'nullable|string',
                'is_loop' => 'nullable|boolean',
                'is_shuffle' => 'nullable|boolean',
                'auto_sync' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('name') && strtolower($request->name) !== strtolower($playlist->name)) {
                $existingPlaylist = Playlist::whereRaw('LOWER(name) = ?', [strtolower($request->name)])
                    ->where('id', '!=', $playlist->id)
                    ->exists();

                if ($existingPlaylist) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Une autre playlist avec ce nom existe déjà'
                    ], 409);
                }
            }

            $playlist->update($request->only([
                'name', 'description', 'type', 'is_loop', 'is_shuffle'
            ]));

            // ✅ Aucune synchronisation ni redémarrage automatique dans update()
            // La synchronisation et le redémarrage doivent être déclenchés manuellement
            // via POST /api/playlists/{playlist}/update-m3u-restart
            $syncResult = [
                'success' => true,
                'message' => 'Synchronisation AzuraCast non déclenchée automatiquement. Utilisez /update-m3u-restart pour synchroniser et redémarrer.'
            ];

            $playlist->load('items.mediaFile');

            return response()->json([
                'success' => true,
                'data' => $playlist,
                'sync' => $syncResult,
                'message' => 'Playlist mise à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        try {
            $playlistName = $playlist->name;
            $playlistId = $playlist->id;
            
            // ✅ Supprimer tous les items de la playlist localement uniquement
            $itemsCount = $playlist->items()->count();
            $playlist->items()->delete();
            
            // Recalculer les stats de la playlist
            $playlist->recalculateStats();
            
            Log::info("Tous les items de la playlist supprimés localement", [
                'playlist_id' => $playlistId,
                'playlist_name' => $playlistName,
                'items_deleted' => $itemsCount
            ]);
            
            // Recharger la playlist pour la réponse
            $playlist->load('items.mediaFile');
            
            return response()->json([
                'success' => true,
                'message' => "Tous les items de la playlist '$playlistName' ont été supprimés localement",
                'data' => $playlist,
                'items_deleted' => $itemsCount
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la suppression des items de la playlist", [
                'playlist_id' => $playlist->id ?? null,
                'playlist_name' => $playlist->name ?? null,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suppression de toutes les playlists désactivée
     */
    public function deleteAll(): JsonResponse
    {
        // ✅ Suppression désactivée : ni dans AzuraCast ni localement
        // La suppression doit être gérée manuellement si nécessaire
        
        return response()->json([
            'success' => false,
            'message' => 'La suppression de toutes les playlists est désactivée. Veuillez gérer la suppression manuellement si nécessaire.'
        ], 403);
    }

    /**
     * Met à jour le fichier M3U de la playlist et redémarre AzuraCast avec Docker
     * 
     * @param Playlist $playlist
     * @return JsonResponse
     */
    public function updateM3UAndRestart(Request $request, Playlist $playlist): JsonResponse
    {
        try {
            // Vérifier si l'exécution asynchrone est demandée
            // ✅ Mode asynchrone par défaut pour éviter les timeouts avec de nombreux fichiers
            $async = $request->input('async', true);

            Log::info("Début mise à jour M3U et redémarrage AzuraCast", [
                'playlist_id' => $playlist->id,
                'playlist_name' => $playlist->name,
                'async' => $async
            ]);

            // Vérifier que la playlist a un azuracast_id
            if (!$playlist->azuracast_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La playlist n\'a pas d\'ID AzuraCast. Veuillez d\'abord synchroniser la playlist.',
                ], 400);
            }

            // Mode asynchrone : dispatcher un job et retourner immédiatement
            if ($async) {
                \App\Jobs\UpdateM3UAndRestartJob::dispatch($playlist->id);
                
                Log::info("Synchronisation M3U dispatchée en arrière-plan", [
                    'playlist_id' => $playlist->id,
                    'playlist_name' => $playlist->name
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Synchronisation M3U et redémarrage AzuraCast lancée en arrière-plan',
                    'async' => true,
                    'data' => [
                        'playlist_id' => $playlist->id,
                        'playlist_name' => $playlist->name,
                        'azuracast_id' => $playlist->azuracast_id,
                        'status' => 'processing'
                    ]
                ]);
            }

            // Mode synchrone : exécution immédiate (comportement par défaut)
            $syncService = new AzuraCastSyncService();

            // Utiliser la méthode du service qui fait tout (M3U + Docker + API)
            $result = $syncService->updateM3UAndRestartService($playlist);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'error' => $result['error'] ?? null
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'async' => false,
                'data' => [
                    'playlist_id' => $playlist->id,
                    'playlist_name' => $playlist->name,
                    'azuracast_id' => $playlist->azuracast_id,
                    'm3u_lines' => $result['m3u_lines'] ?? 0,
                    'files_copied' => $result['files_copied'] ?? 0,
                    'import_results' => $result['import_results'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour M3U et redémarrage", [
                'playlist_id' => $playlist->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour M3U et redémarrage: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifie le statut de synchronisation de la playlist
     * 
     * @param Playlist $playlist
     * @return JsonResponse
     */
    public function syncStatus(Playlist $playlist): JsonResponse
    {
        try {
            $playlist->refresh(); // Recharger depuis la base de données pour avoir le statut à jour
            
            $syncStatus = $playlist->sync_status ?? 'pending';
            $lastSyncAt = $playlist->last_sync_at;
            
            // Déterminer si la synchronisation est terminée
            $isCompleted = in_array($syncStatus, ['synced', 'error']);
            
            // Déterminer si une synchronisation est en cours
            // Si le statut est 'pending' ET qu'il y a eu une synchronisation récente (< 10 minutes)
            $isProcessing = false;
            if ($syncStatus === 'pending' && $lastSyncAt) {
                $minutesSinceLastSync = now()->diffInMinutes($lastSyncAt);
                // Si la dernière sync était il y a moins de 10 minutes, on considère qu'elle est peut-être en cours
                // Mais aussi vérifier s'il y a un job en cours pour cette playlist
                $isProcessing = $minutesSinceLastSync < 10;
            }
            
            // Déterminer le message descriptif
            $message = match($syncStatus) {
                'synced' => 'Synchronisation terminée avec succès',
                'error' => 'Erreur lors de la synchronisation',
                'pending' => $isProcessing ? 'Synchronisation en cours...' : 'En attente de synchronisation',
                default => 'Statut inconnu'
            };
            
            // ✅ Diagnostic : Vérifier le nombre de fichiers
            $syncService = new AzuraCastSyncService();
            $playlist->load('items.mediaFile');
            
            $localAudioFilesCount = $playlist->items()->whereHas('mediaFile', function($query) {
                $query->where('file_type', 'audio');
            })->count();
            
            // Vérifier dans AzuraCast
            $azuracastFilesCount = 0;
            $missingFiles = [];
            $filesWithAzuracastId = 0;
            
            if ($playlist->azuracast_id) {
                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(30)
                        ->withHeaders(['X-API-Key' => config('services.azuracast.api_key')])
                        ->get(config('services.azuracast.base_url') . '/api/station/' . config('services.azuracast.station_id') . '/playlist/' . $playlist->azuracast_id);
                    
                    if ($response->successful()) {
                        $playlistData = $response->json();
                        $azuracastFilesCount = $playlistData['num_songs'] ?? count($playlistData['media'] ?? []);
                    }
                } catch (\Exception $e) {
                    Log::warning("Impossible de vérifier la playlist AzuraCast: " . $e->getMessage());
                }
                
                // Vérifier les fichiers manquants dans AzuraCast
                foreach ($playlist->items as $item) {
                    if ($item->mediaFile && $item->mediaFile->file_type === 'audio') {
                        $fileName = basename($item->mediaFile->original_name);
                        if ($item->mediaFile->azuracast_id) {
                            $filesWithAzuracastId++;
                        }
                        // Vérifier via l'API AzuraCast si le fichier existe
                        $reflection = new \ReflectionClass($syncService);
                        $method = $reflection->getMethod('checkFileExistsInAzuraCast');
                        $method->setAccessible(true);
                        if (!$method->invoke($syncService, $fileName)) {
                            $missingFiles[] = $fileName;
                        }
                    }
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'playlist_id' => $playlist->id,
                    'playlist_name' => $playlist->name,
                    'sync_status' => $syncStatus,
                    'is_completed' => $isCompleted,
                    'is_processing' => $isProcessing,
                    'last_sync_at' => $lastSyncAt ? $lastSyncAt->toIso8601String() : null,
                    'message' => $message,
                    'azuracast_id' => $playlist->azuracast_id,
                    'diagnostic' => [
                        'local_files_count' => $localAudioFilesCount,
                        'azuracast_files_count' => $azuracastFilesCount,
                        'files_with_azuracast_id' => $filesWithAzuracastId,
                        'missing_files_count' => count($missingFiles),
                        'missing_files' => array_slice($missingFiles, 0, 10), // Premiers 10 fichiers manquants
                        'is_empty_in_azuracast' => $azuracastFilesCount === 0 && $localAudioFilesCount > 0,
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification du statut de synchronisation", [
                'playlist_id' => $playlist->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crée dans AzuraCast toutes les playlists qui n'ont pas encore d'azuracast_id
     */
    public function createMissingPlaylistsInAzuraCast(): JsonResponse
    {
        try {
            // Récupérer toutes les playlists qui n'ont pas d'azuracast_id
            $playlistsWithoutAzuraCast = Playlist::whereNull('azuracast_id')->get();
            
            if ($playlistsWithoutAzuraCast->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Toutes les playlists sont déjà créées dans AzuraCast',
                    'created_count' => 0,
                    'playlists' => []
                ]);
            }

            $createdPlaylists = [];
            $failedPlaylists = [];

            foreach ($playlistsWithoutAzuraCast as $playlist) {
                try {
                    // Créer la playlist dans AzuraCast via l'API directement
                    $response = Http::timeout(30)->withHeaders([
                        'X-API-Key' => config('services.azuracast.api_key')
                    ])->post(config('services.azuracast.base_url') . '/api/station/' . config('services.azuracast.station_id') . '/playlists', [
                        'name' => $playlist->name,
                        'type' => 'default',
                        'source' => 'songs',
                        'order' => $playlist->is_shuffle ? 'random' : 'sequential',
                        'weight' => 3,
                        'is_enabled' => true,
                        'include_in_automation' => true
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $playlist->update(['azuracast_id' => $data['id']]);
                        
                        $createdPlaylists[] = [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'azuracast_id' => $data['id']
                        ];
                        
                        Log::info("Playlist créée dans AzuraCast", [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'azuracast_id' => $data['id']
                        ]);
                    } else {
                        $failedPlaylists[] = [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'error' => $response->body()
                        ];
                        
                        Log::error("Erreur lors de la création de la playlist dans AzuraCast", [
                            'playlist_id' => $playlist->id,
                            'playlist_name' => $playlist->name,
                            'status' => $response->status(),
                            'error' => $response->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    $failedPlaylists[] = [
                        'playlist_id' => $playlist->id,
                        'playlist_name' => $playlist->name,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error("Exception lors de la création de la playlist dans AzuraCast", [
                        'playlist_id' => $playlist->id,
                        'playlist_name' => $playlist->name,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->json([
                'success' => count($failedPlaylists) === 0,
                'message' => count($createdPlaylists) . ' playlist(s) créée(s) dans AzuraCast' . 
                            (count($failedPlaylists) > 0 ? ', ' . count($failedPlaylists) . ' échec(s)' : ''),
                'created_count' => count($createdPlaylists),
                'failed_count' => count($failedPlaylists),
                'created_playlists' => $createdPlaylists,
                'failed_playlists' => $failedPlaylists
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la création des playlists manquantes dans AzuraCast: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création des playlists: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nettoie AzuraCast en supprimant les fichiers non utilisés
     * Supprime uniquement les fichiers qui :
     * - Ne sont pas dans la base de données locale
     * - Ne sont pas utilisés dans des playlists AzuraCast
     */
    public function cleanAzuraCast(): JsonResponse
    {
        try {
            Log::info("Début du nettoyage AzuraCast");

            $azuracastService = new AzuraCastService();
            $baseUrl = config('services.azuracast.base_url');
            $apiKey = config('services.azuracast.api_key');
            $stationId = config('services.azuracast.station_id');

            // 1. Récupérer tous les fichiers AzuraCast
            $response = Http::withHeaders([
                'X-API-Key' => $apiKey
            ])->timeout(30)->get("{$baseUrl}/api/station/{$stationId}/files");

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de récupérer les fichiers AzuraCast: ' . $response->body()
                ], 500);
            }

            $azuracastFiles = $response->json();
            Log::info("Fichiers trouvés dans AzuraCast", ['count' => count($azuracastFiles)]);

            // 2. Récupérer les IDs des fichiers locaux
            $localFileIds = MediaFile::where('file_type', 'audio')
                ->whereNotNull('azuracast_id')
                ->pluck('azuracast_id')
                ->toArray();

            Log::info("Fichiers locaux avec azuracast_id", ['count' => count($localFileIds), 'ids' => $localFileIds]);

            // 3. Récupérer tous les fichiers utilisés dans les playlists AzuraCast
            $playlistsResponse = Http::withHeaders([
                'X-API-Key' => $apiKey
            ])->timeout(30)->get("{$baseUrl}/api/station/{$stationId}/playlists");

            $filesInPlaylists = [];
            if ($playlistsResponse->successful()) {
                $playlists = $playlistsResponse->json();
                foreach ($playlists as $playlist) {
                    $playlistId = $playlist['id'] ?? null;
                    if ($playlistId) {
                        $playlistDetailResponse = Http::withHeaders([
                            'X-API-Key' => $apiKey
                        ])->timeout(30)->get("{$baseUrl}/api/station/{$stationId}/playlist/{$playlistId}");

                        if ($playlistDetailResponse->successful()) {
                            $playlistData = $playlistDetailResponse->json();
                            if (isset($playlistData['media']) && is_array($playlistData['media'])) {
                                foreach ($playlistData['media'] as $media) {
                                    $mediaId = $media['id'] ?? null;
                                    if ($mediaId) {
                                        $filesInPlaylists[] = $mediaId;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $filesInPlaylists = array_unique($filesInPlaylists);
            Log::info("Fichiers utilisés dans les playlists AzuraCast", ['count' => count($filesInPlaylists)]);

            // 4. Identifier les fichiers à supprimer
            $filesToDelete = [];
            foreach ($azuracastFiles as $file) {
                $fileId = $file['id'] ?? null;
                $fileName = basename($file['path'] ?? $file['name'] ?? 'N/A');

                // Supprimer si :
                // - Le fichier n'est pas dans la base locale
                // - Le fichier n'est pas utilisé dans une playlist AzuraCast
                if ($fileId && !in_array($fileId, $localFileIds) && !in_array($fileId, $filesInPlaylists)) {
                    $filesToDelete[] = [
                        'id' => $fileId,
                        'name' => $fileName
                    ];
                }
            }

            Log::info("Fichiers identifiés pour suppression", ['count' => count($filesToDelete)]);

            // 5. Supprimer les fichiers
            $deletedCount = 0;
            $failedCount = 0;
            $deletedFiles = [];
            $failedFiles = [];

            foreach ($filesToDelete as $file) {
                try {
                    $deleteResponse = Http::withHeaders([
                        'X-API-Key' => $apiKey
                    ])->timeout(30)->delete("{$baseUrl}/api/station/{$stationId}/file/{$file['id']}");

                    if ($deleteResponse->successful()) {
                        $deletedCount++;
                        $deletedFiles[] = $file['name'];
                        Log::info("Fichier supprimé", ['id' => $file['id'], 'name' => $file['name']]);
                    } else {
                        $failedCount++;
                        $failedFiles[] = [
                            'name' => $file['name'],
                            'error' => $deleteResponse->body()
                        ];
                        Log::warning("Échec suppression fichier", [
                            'id' => $file['id'],
                            'name' => $file['name'],
                            'error' => $deleteResponse->body()
                        ]);
                    }

                    // Petite pause pour ne pas surcharger l'API
                    usleep(200000); // 0.2 secondes
                } catch (\Exception $e) {
                    $failedCount++;
                    $failedFiles[] = [
                        'name' => $file['name'],
                        'error' => $e->getMessage()
                    ];
                    Log::error("Exception lors de la suppression", [
                        'id' => $file['id'],
                        'name' => $file['name'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 6. Déclencher un scan pour mettre à jour la bibliothèque
            if ($deletedCount > 0) {
                try {
                    $azuracastService->triggerMediaScan();
                    Log::info("Scan des médias déclenché après suppression");
                } catch (\Exception $e) {
                    Log::warning("Impossible de déclencher le scan après suppression: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => $failedCount === 0,
                'message' => "Nettoyage terminé: {$deletedCount} fichier(s) supprimé(s)" . 
                            ($failedCount > 0 ? ", {$failedCount} échec(s)" : ""),
                'data' => [
                    'total_files_in_azuracast' => count($azuracastFiles),
                    'local_files_count' => count($localFileIds),
                    'files_in_playlists_count' => count($filesInPlaylists),
                    'files_to_delete_count' => count($filesToDelete),
                    'deleted_count' => $deletedCount,
                    'failed_count' => $failedCount,
                    'deleted_files' => array_slice($deletedFiles, 0, 20), // Limiter à 20 pour la réponse
                    'failed_files' => $failedFiles
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Erreur lors du nettoyage AzuraCast: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du nettoyage: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

