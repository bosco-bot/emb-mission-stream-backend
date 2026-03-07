<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\UploadSession;
use App\Models\MediaFileRelation;
use App\Models\UploadSessionFile;
use App\Services\MediaMetadataService;
use Illuminate\Http\Request;
use App\Jobs\ProcessMediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use App\Services\AntMediaVoDService;
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    public function __construct(private MediaMetadataService $metadataService)
    {
    }

    /**
     * Lister tous les fichiers de la médiathèque
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Détecter automatiquement le type de fichier depuis l'URL
            $urlSegments = explode("/", $request->getPathInfo());
            if (in_array("audio", $urlSegments)) {
                $request->merge(["file_type" => "audio"]);
            } elseif (in_array("video", $urlSegments)) {
                $request->merge(["file_type" => "video"]);
            } elseif (in_array("image", $urlSegments)) {
                $request->merge(["file_type" => "image"]);
            }

            $query = MediaFile::query();
            
            // Filtres
            if ($request->has('file_type')) {
                $query->where('file_type', $request->file_type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('filename', 'like', "%{$search}%")
                      ->orWhere('original_name', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $files = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $files->items(),
                'pagination' => [
                    'total' => $files->total(),
                    'per_page' => $files->perPage(),
                    'current_page' => $files->currentPage(),
                    'last_page' => $files->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des fichiers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer les fichiers en cours d'importation (comme dans votre capture)
     */
    public function getImportingFiles(): JsonResponse
    {
        try {
            $importingFiles = MediaFile::importing()
                ->with(['audioRelations', 'videoRelations'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'filename' => $file->filename,
                        'original_name' => $file->original_name,
                        'file_type' => $file->file_type,
                        'file_size_formatted' => $file->file_size_formatted,
                        'status' => $file->status,
                        'status_text' => $file->status_text,
                        'progress' => $file->progress,
                        'error_message' => $file->error_message,
                        'estimated_time_remaining' => $file->estimated_time_remaining,
                        'bytes_uploaded' => $file->bytes_uploaded,
                        'bytes_total' => $file->bytes_total,
                        'file_url' => $this->generateFileUrl($file),
                        'created_at' => $file->created_at,
                        'updated_at' => $file->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Fichiers en cours d\'importation récupérés',
                'data' => $importingFiles
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des fichiers en cours',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les fichiers avec erreur
     */
    public function getErrorFiles(): JsonResponse
    {
        try {
            $errorFiles = MediaFile::withErrors()
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'filename' => $file->filename,
                        'original_name' => $file->original_name,
                        'file_type' => $file->file_type,
                        'file_size_formatted' => $file->file_size_formatted,
                        'status' => $file->status,
                        'error_message' => $file->error_message,
                        'file_url' => $this->generateFileUrl($file),
                        'created_at' => $file->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Fichiers avec erreur récupérés',
                'data' => $errorFiles
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des fichiers en erreur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les fichiers terminés
     */
    public function getCompletedFiles(): JsonResponse
    {
        try {
            $completedFiles = MediaFile::completed()
                ->with(['audioRelations', 'videoRelations'])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'filename' => $file->filename,
                        'original_name' => $file->original_name,
                        'file_type' => $file->file_type,
                        'file_size_formatted' => $file->file_size_formatted,
                        'status' => $file->status,
                        'thumbnail_url' => $file->thumbnail_url ? $this->generateThumbnailUrl($file) : null,
                        'has_thumbnail' => $file->hasThumbnail(),
                        'duration' => $file->formatted_duration,
                        'file_url' => $this->generateFileUrl($file),
                        'created_at' => $file->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Fichiers terminés récupérés',
                'data' => $completedFiles
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des fichiers terminés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer un fichier spécifique
     */
    public function show(MediaFile $mediaFile): JsonResponse
    {
        try {
            $mediaFile->load(['audioRelations', 'videoRelations', 'thumbnailRelations']);
            
            $mediaFile->file_url = $this->generateFileUrl($mediaFile);
            
            return response()->json([
                'success' => true,
                'message' => 'Fichier récupéré avec succès',
                'data' => $mediaFile
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler l'importation d'un fichier
     */
    public function cancelImport(MediaFile $mediaFile): JsonResponse
    {
        try {
            if (!$mediaFile->isProcessing()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier n\'est pas en cours de traitement',
                ], 400);
            }

            if (Storage::disk('media')->exists($mediaFile->file_path)) {
                Storage::disk('media')->delete($mediaFile->file_path);
            }

            $mediaFile->delete();

            return response()->json([
                'success' => true,
                'message' => 'Importation annulée avec succès'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de l\'importation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Relancer l'importation d'un fichier en erreur
     */
    public function retryImport(MediaFile $mediaFile): JsonResponse
    {
        try {
            if (!$mediaFile->hasError()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le fichier n\'est pas en erreur',
                ], 400);
            }

            $mediaFile->update([
                'status' => 'importing',
                'progress' => 0,
                'error_message' => null,
                'bytes_uploaded' => 0,
            ]);

            $mediaFile->file_url = $this->generateFileUrl($mediaFile);
            
            ProcessMediaFile::dispatch($mediaFile);
            // Log::info("Upload to AzuraCast triggered");
            
            return response()->json([
                'success' => true,
                'message' => 'Réimportation lancée avec succès',
                'data' => $mediaFile->fresh()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du relancement de l\'importation',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /*
     Supprimer un fichier
     
    public function destroy(MediaFile $mediaFile): JsonResponse
    {
        try {
            // Vérifier les dépendances avec les playlists
            $playlistItems = \App\Models\PlaylistItem::where('media_file_id', $mediaFile->id)->get();
            
            if ($playlistItems->count() > 0) {
                $playlistNames = $playlistItems->map(function ($item) {
                    return $item->playlist->name ?? 'Playlist inconnue';
                })->unique()->toArray();
                
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer le fichier car il est utilisé dans des playlists',
                    'error' => 'File is used in playlists',
                    'details' => [
                        'file_name' => $mediaFile->original_name,
                        'playlists_count' => $playlistItems->count(),
                        'playlists' => $playlistNames,
                        'playlist_items' => $playlistItems->pluck('id')->toArray()
                    ]
                ], 422);
            }

            // Supprimer le fichier physique
            if (Storage::disk('media')->exists($mediaFile->file_path)) {
                Storage::disk('media')->delete($mediaFile->file_path);
            }

            // Supprimer la miniature si elle existe
            if ($mediaFile->thumbnail_path && Storage::exists($mediaFile->thumbnail_path)) {
                Storage::delete($mediaFile->thumbnail_path);
            }

            // Supprimer l'enregistrement de la base de données
            $mediaFile->delete();

            return response()->json([
                'success' => true,
                'message' => 'Fichier supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    */

    public function destroy(MediaFile $mediaFile): JsonResponse
    {
        try {
            // Vérifier les dépendances avec les playlists AUDIO
            $playlistItems = \App\Models\PlaylistItem::where('media_file_id', $mediaFile->id)->get();

            // Vérifier les dépendances avec les playlists WebTV
            $webtvPlaylistItems = \App\Models\WebTVPlaylistItem::where('video_file_id', $mediaFile->id)->get();

            // Vérifier si le fichier est utilisé dans l'une ou l'autre type de playlist
            if ($playlistItems->count() > 0 || $webtvPlaylistItems->count() > 0) {
                // Collecter les noms des playlists audio
                $audioPlaylistNames = $playlistItems->map(function ($item) {
                    return $item->playlist->name ?? 'Playlist inconnue';
                })->unique()->toArray();

                // Collecter les noms des playlists WebTV
                $webtvPlaylistNames = $webtvPlaylistItems->map(function ($item) {
                    return $item->playlist->name ?? 'Playlist WebTV inconnue';
                })->unique()->toArray();

                // Compter le total de playlists
                $totalPlaylistsCount = $playlistItems->count() + $webtvPlaylistItems->count();

                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer le fichier car il est utilisé dans des playlists',
                    'error' => 'File is used in playlists',
                    'details' => [
                        'file_name' => $mediaFile->original_name,
                        'total_playlists_count' => $totalPlaylistsCount,
                        'audio_playlists_count' => $playlistItems->count(),
                        'webtv_playlists_count' => $webtvPlaylistItems->count(),
                        'audio_playlists' => $audioPlaylistNames,
                        'webtv_playlists' => $webtvPlaylistNames,
                        'playlist_items' => $playlistItems->pluck('id')->toArray(),
                        'webtv_playlist_items' => $webtvPlaylistItems->pluck('id')->toArray()
                    ]
                ], 422);
            }

            // Supprimer le HLS pré-converti éventuel (vod_mf_{media_file_id}) si présent
            try {
                $vodService = new AntMediaVoDService();
                $vodName = $vodService->buildVodNameForMediaFile((int) $mediaFile->id);
                $vodDir = '/usr/local/antmedia/webapps/LiveApp/streams/' . $vodName;
                if (File::isDirectory($vodDir)) {
                    File::deleteDirectory($vodDir);
                    Log::info('🗑️ Suppression du VoD HLS pré-converti du MediaFile', [
                        'media_file_id' => $mediaFile->id,
                        'vod_dir' => $vodDir,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('⚠️ Échec suppression VoD HLS pré-converti du MediaFile: ' . $e->getMessage(), [
                    'media_file_id' => $mediaFile->id,
                ]);
            }

            // Supprimer le fichier physique
            if (Storage::disk('media')->exists($mediaFile->file_path)) {
                Storage::disk('media')->delete($mediaFile->file_path);
            }

            // Supprimer la miniature si elle existe
            if ($mediaFile->thumbnail_path && Storage::exists($mediaFile->thumbnail_path)) {
                Storage::delete($mediaFile->thumbnail_path);
            }

            // Supprimer l'enregistrement de la base de données
            $mediaFile->delete();

            return response()->json([
                'success' => true,
                'message' => 'Fichier supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle session d'upload
     */
    public function createUploadSession(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'auto_match_thumbnails' => 'boolean',
                'generate_missing_thumbnails' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $session = UploadSession::create([
                'user_id' => auth()->id(),
                'auto_match_thumbnails' => $request->get('auto_match_thumbnails', true),
                'generate_missing_thumbnails' => $request->get('generate_missing_thumbnails', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Session d\'upload créée avec succès',
                'data' => [
                    'session_id' => $session->id,
                    'session_token' => $session->session_token,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload d'un fichier unique
     */
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            Log::info("📤 Upload démarré", [
                'file_size' => $request->file('file')?->getSize(),
                'file_name' => $request->file('file')?->getClientOriginalName(),
            ]);
            
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:2147483648', // 2 GB max (correspond à la limite PHP)
                'session_token' => 'required|string|exists:upload_sessions,session_token',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $session = UploadSession::where('session_token', $request->session_token)->first();

            $fileType = $this->determineFileType($file->getMimeType());
            
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = "media/{$fileType}s/" . date('Y/m/d') . '/' . $filename;

            //$storedPath = $file->storeAs("media/{$fileType}s/" . date('Y/m/d'), $filename);

            //CODE CORRIGÉ: Utiliser Storage::disk()->putFileAs() au lieu de storeAs()
            $targetPath = "{$fileType}s/" . date('Y/m/d');
            Log::info("💾 Stockage fichier", ['path' => $targetPath, 'filename' => $filename]);
            
            // Utiliser Storage::disk() directement pour plus de contrôle
            $storedPath = Storage::disk('media')->putFileAs($targetPath, $file, $filename);
            
            // Vérifier que le stockage a réussi
            if ($storedPath === false || empty($storedPath)) {
                Log::error("❌ Échec stockage fichier", [
                    'stored_path' => $storedPath,
                    'target_path' => $targetPath,
                    'filename' => $filename,
                ]);
                throw new \Exception("Échec du stockage du fichier");
            }
            
            Log::info("✅ Fichier stocké", ['stored_path' => $storedPath, 'type' => gettype($storedPath)]);

            $fileUrl = $this->generateUrlForFileType($fileType, $storedPath);
            Log::info("🔗 URL générée", ['file_url' => $fileUrl]);

            $durationSeconds = null;
            if (in_array($fileType, ['video', 'audio'], true)) {
                try {
                    // S'assurer que le fichier est complètement écrit sur le disque
                    $absolutePath = Storage::disk('media')->path($storedPath);
                    
                    // Vérifier que le fichier existe et a une taille > 0
                    if (!file_exists($absolutePath)) {
                        Log::warning("⚠️ Fichier non trouvé pour extraction durée", [
                            'stored_path' => $storedPath,
                            'absolute_path' => $absolutePath
                        ]);
                    } else {
                        // Attendre un peu pour s'assurer que le fichier est complètement écrit
                        $maxWait = 5; // 5 secondes max
                        $waitTime = 0;
                        while ($waitTime < $maxWait && !is_readable($absolutePath)) {
                            usleep(100000); // 0.1 secondes
                            $waitTime += 0.1;
                        }
                        
                        Log::info("🎬 Extraction durée pour: {$storedPath}", [
                            'absolute_path' => $absolutePath,
                            'file_exists' => file_exists($absolutePath),
                            'file_size' => file_exists($absolutePath) ? filesize($absolutePath) : 0,
                            'is_readable' => is_readable($absolutePath),
                        ]);
                        
                        $durationFloat = $this->metadataService->getDurationInSeconds($storedPath);
                        if ($durationFloat !== null) {
                            // Convertir en entier (secondes) pour le stockage (le cast est integer)
                            $durationSeconds = (int) round($durationFloat);
                            Log::info("✅ Durée extraite: {$durationFloat} secondes (arrondi: {$durationSeconds}s)");
                        } else {
                            Log::warning("⚠️ Durée non extraite (ffprobe a retourné null)", [
                                'stored_path' => $storedPath,
                                'absolute_path' => $absolutePath
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("⚠️ Échec extraction durée (non bloquant)", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'stored_path' => $storedPath ?? null
                    ]);
                    // Ne pas bloquer l'upload si l'extraction de durée échoue
                    $durationSeconds = null;
                }
            }

            Log::info("💾 Création MediaFile en base");
            $mediaFile = MediaFile::create([
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'file_url' => $fileUrl,
                'file_type' => $fileType,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_size_formatted' => $this->formatBytes($file->getSize()),
                'duration' => $durationSeconds,
                'status' => 'importing',
                'progress' => 0,
                'bytes_total' => $file->getSize(),
                'bytes_uploaded' => $file->getSize(),
            ]);
            Log::info("✅ MediaFile créé", ['id' => $mediaFile->id]);

            Log::info("📎 Création session file");
            $sessionFile = UploadSessionFile::createForSession($session, $mediaFile);
            Log::info("✅ Session file créée");

            // Déclenchement automatique de la pré-conversion HLS (non-bloquant via shutdown)
            try {
                $mediaId = $mediaFile->id;
                register_shutdown_function(function () use ($mediaId) {
                    try {
                        $mf = \App\Models\MediaFile::find($mediaId);
                        if ($mf && $mf->file_type === 'video') {
                            $svc = new \App\Services\AntMediaVoDService();
                            $res = $svc->createVodForMediaFile($mf);
                            \Illuminate\Support\Facades\Log::info('🎬 Pré-conversion HLS déclenchée après upload', [
                                'media_file_id' => $mediaId,
                                'success' => $res['success'] ?? null,
                                'message' => $res['message'] ?? null,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('⚠️ Pré-conversion HLS post-upload échouée: ' . $e->getMessage(), [
                            'media_file_id' => $mediaId
                        ]);
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('⚠️ Impossible d\'enregistrer la pré-conversion post-upload: ' . $e->getMessage());
            }

            Log::info("🚀 Dispatch ProcessMediaFile");
            ProcessMediaFile::dispatch($mediaFile);
            Log::info("✅ Job dispatché");

            Log::info("✅ Upload terminé avec succès");
            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès',
                'data' => [
                    'media_file' => $mediaFile,
                    'session_file' => $sessionFile,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            Log::error("❌ Erreur upload", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple de fichiers
     */
    public function uploadMultipleFiles(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'files' => 'required|array|min:1',
                'files.*' => 'file|max:2147483648', // 2 GB max (correspond à la limite PHP)
                'session_token' => 'required|string|exists:upload_sessions,session_token',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $files = $request->file('files');
            $session = UploadSession::where('session_token', $request->session_token)->first();
            $uploadedFiles = [];

            $videoIdsForPreconvert = [];
            foreach ($files as $index => $file) {
                $fileType = $this->determineFileType($file->getMimeType());
                
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $filePath = "media/{$fileType}s/" . date('Y/m/d') . '/' . $filename;

                //$storedPath = $file->storeAs("media/{$fileType}s/" . date('Y/m/d'), $filename);
                
                //CODE CORRIGÉ
                $storedPath = $file->storeAs("{$fileType}s/" . date('Y/m/d'), $filename, 'media');

                $fileUrl = $this->generateUrlForFileType($fileType, $storedPath);

                $durationSeconds = null;
                if (in_array($fileType, ['video', 'audio'], true)) {
                    $durationSeconds = $this->metadataService->getDurationInSeconds($storedPath);
                }

                $mediaFile = MediaFile::create([
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'file_url' => $fileUrl,
                    'file_type' => $fileType,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_size_formatted' => $this->formatBytes($file->getSize()),
                    'duration' => $durationSeconds,
                    'status' => 'importing',
                    'progress' => 0,
                    'bytes_total' => $file->getSize(),
                    'bytes_uploaded' => $file->getSize(),
                ]);

                $sessionFile = UploadSessionFile::createForSession($session, $mediaFile, $index + 1);
                
                $uploadedFiles[] = [
                    'media_file' => $mediaFile,
                    'session_file' => $sessionFile,
                ];

                // Collecter pour pré-conversion post-réponse (non bloquant)
                if ($fileType === 'video') {
                    $videoIdsForPreconvert[] = $mediaFile->id;
                }
            }

            // Déclenchement automatique de la pré-conversion HLS pour les vidéos (non-bloquant)
            if (!empty($videoIdsForPreconvert)) {
                try {
                    $ids = $videoIdsForPreconvert;
                    register_shutdown_function(function () use ($ids) {
                        foreach ($ids as $mediaId) {
                            try {
                                $mf = \App\Models\MediaFile::find($mediaId);
                                if ($mf && $mf->file_type === 'video') {
                                    $svc = new \App\Services\AntMediaVoDService();
                                    $res = $svc->createVodForMediaFile($mf);
                                    \Illuminate\Support\Facades\Log::info('🎬 Pré-conversion HLS (multi) post-upload', [
                                        'media_file_id' => $mediaId,
                                        'success' => $res['success'] ?? null,
                                        'message' => $res['message'] ?? null,
                                    ]);
                                }
                            } catch (\Throwable $e) {
                                \Illuminate\Support\Facades\Log::warning('⚠️ Pré-conversion HLS (multi) échouée: ' . $e->getMessage(), [
                                    'media_file_id' => $mediaId
                                ]);
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    Log::warning('⚠️ Impossible d’enregistrer la pré-conversion (multi): ' . $e->getMessage());
                }
            }

            $session->update([
                'total_files' => $session->total_files + count($files),
                'uploaded_files' => $session->uploaded_files + count($files),
            ]);

            return response()->json([
                'success' => true,
                'message' => count($files) . ' fichiers uploadés avec succès',
                'data' => $uploadedFiles
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload des fichiers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer uniquement les fichiers audio
     */
    public function getAudioFiles(Request $request): JsonResponse
    {
        $request->merge(['file_type' => 'audio']);
        return $this->index($request);
    }

    /**
     * Récupérer uniquement les fichiers vidéo
     */
    public function getVideoFiles(Request $request): JsonResponse
    {
        $request->merge(['file_type' => 'video']);
        return $this->index($request);
    }

    /**
     * Récupérer uniquement les fichiers image
     */
    public function getImageFiles(Request $request): JsonResponse
    {
        $request->merge(['file_type' => 'image']);
        return $this->index($request);
    }

    /**
     * Générer l'URL d'un fichier selon son type
     */
    private function generateFileUrl(MediaFile $file): string
    {
        return $this->generateUrlForFileType($file->file_type, $file->file_path);
    }

    /**
     * Générer l'URL selon le type de fichier
     */
    private function generateUrlForFileType(string $fileType, string $filePath): string
    {
        $baseUrl = match($fileType) {
            'video' => 'https://tv.embmission.com/storage/media/',
            'audio' => 'https://radio.embmission.com/storage/media/',
            'image' => 'https://radio.embmission.com/storage/media/',
            default => 'https://radio.embmission.com/storage/media/',
        };

        return $baseUrl . $filePath;
    }

    /**
     * Générer l'URL d'un thumbnail
     */
    private function generateThumbnailUrl(MediaFile $file): string
    {
        if (!$file->thumbnail_path) {
            return '';
        }

        return 'https://radio.embmission.com/storage/media/' . $file->thumbnail_path;
    }

    /**
     * Déterminer le type de fichier à partir du MIME type
     */
    private function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        
        throw new \InvalidArgumentException("Type de fichier non supporté: {$mimeType}");
    }

    /**
     * Formater la taille en bytes
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Mettre à jour un fichier média
     */
    public function update(Request $request, MediaFile $mediaFile): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'original_name' => 'sometimes|string|max:255',
                'file_type' => 'sometimes|string|in:audio,video,image',
                'metadata' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($request->has('original_name')) {
                $mediaFile->original_name = $request->original_name;
            }
            
            if ($request->has('file_type')) {
                $mediaFile->file_type = $request->file_type;
            }
            
            if ($request->has('metadata')) {
                $mediaFile->metadata = json_encode($request->metadata);
            }

            $mediaFile->save();

            return response()->json([
                'success' => true,
                'message' => 'Fichier mis à jour avec succès',
                'data' => $mediaFile->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mettre à jour un fichier média (version Flutter Web)
     */
    public function updateFlutter(Request $request, MediaFile $mediaFile): JsonResponse
    {
        try {
            // Validation selon les specs Flutter : original_name et file_type REQUIS
            $validator = Validator::make($request->all(), [
                'original_name' => 'required|string|max:255',
                'file_type' => 'required|string|in:audio,video,image',
                'metadata' => 'sometimes|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Mettre à jour les champs
            $mediaFile->original_name = $request->original_name;
            $mediaFile->file_type = $request->file_type;
            
            if ($request->has('metadata')) {
                $mediaFile->metadata = json_encode($request->metadata);
            }

            $mediaFile->save();
            
            // Recharger pour avoir les données à jour
            $mediaFile->refresh();

            // Format de réponse selon Flutter : data.media_file
            return response()->json([
                'success' => true,
                'message' => 'Fichier modifié avec succès',
                'data' => [
                    'media_file' => [
                        'id' => $mediaFile->id,
                        'filename' => $mediaFile->filename,
                        'original_name' => $mediaFile->original_name,
                        'file_type' => $mediaFile->file_type,
                        'file_size_formatted' => $mediaFile->file_size_formatted,
                        'status' => $mediaFile->status,
                        'status_text' => $this->getStatusText($mediaFile->status),
                        'progress' => $mediaFile->progress,
                        'file_url' => $mediaFile->file_url,
                        'metadata' => $mediaFile->metadata ? json_decode($mediaFile->metadata, true) : null,
                        'created_at' => $mediaFile->created_at,
                        'updated_at' => $mediaFile->updated_at,
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du fichier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le texte du statut pour Flutter
     */
    private function getStatusText(string $status): string
    {
        return match($status) {
            'importing' => 'En cours d\'importation',
            'completed' => 'Terminé',
            'error' => 'Erreur',
            default => $status,
        };
    }

    /**
     * Upload d'un chunk de fichier
     * 
     * Reçoit un morceau du fichier et le stocke temporairement
     */
    public function uploadChunk(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file',
                'session_token' => 'required|string|exists:upload_sessions,session_token',
                'upload_id' => 'required|string',
                'chunk_index' => 'required|integer|min:0',
                'total_chunks' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $file = $request->file('file');
            $uploadId = $request->upload_id;
            $chunkIndex = $request->chunk_index;
            $totalChunks = $request->total_chunks;

            // Créer le répertoire temporaire pour cet upload
            $tempDir = storage_path("app/chunks/{$uploadId}");
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Stocker le chunk
            $chunkPath = "{$tempDir}/chunk_{$chunkIndex}";
            $file->storeAs("chunks/{$uploadId}", "chunk_{$chunkIndex}", 'local');

            // Calculer la progression
            $progress = (($chunkIndex + 1) / $totalChunks) * 100;

            return response()->json([
                'success' => true,
                'message' => "Chunk {$chunkIndex}/{$totalChunks} uploadé avec succès",
                'data' => [
                    'upload_id' => $uploadId,
                    'chunk_index' => $chunkIndex,
                    'total_chunks' => $totalChunks,
                    'progress' => round($progress, 2),
                    'is_complete' => ($chunkIndex + 1) == $totalChunks
                ]
            ], 200);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur upload chunk: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du chunk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finaliser l'upload en assemblant les chunks
     * 
     * Rassemble tous les chunks, crée le fichier final et lance le traitement
     */
    public function finalizeChunkUpload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_token' => 'required|string|exists:upload_sessions,session_token',
                'upload_id' => 'required|string',
                'filename' => 'required|string',
                'mime_type' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadId = $request->upload_id;
            $filename = $request->filename;
            $mimeType = $request->mime_type;
            $session = UploadSession::where('session_token', $request->session_token)->first();

            // Localiser tous les chunks
            $chunkDir = storage_path("app/chunks/{$uploadId}");
            if (!is_dir($chunkDir)) {
                throw new \Exception("Répertoire chunks introuvable pour upload_id: {$uploadId}");
            }

            // Lire tous les chunks et les trier
            $chunks = glob("{$chunkDir}/chunk_*");
            if (empty($chunks)) {
                throw new \Exception("Aucun chunk trouvé pour upload_id: {$uploadId}");
            }

            // Trier par index
            usort($chunks, function($a, $b) {
                preg_match('/chunk_(\d+)/', basename($a), $matchesA);
                preg_match('/chunk_(\d+)/', basename($b), $matchesB);
                return ((int)($matchesA[1] ?? 0)) - ((int)($matchesB[1] ?? 0));
            });

            // Assembler le fichier
            $finalFilename = Str::uuid() . '.' . pathinfo($filename, PATHINFO_EXTENSION);
            Log::info("🔧 Finalisation chunks", [
                'upload_id' => $uploadId,
                'filename' => $filename,
                'mime_type' => $mimeType,
            ]);
            
            $fileType = $this->determineFileType($mimeType);
            Log::info("✅ Type déterminé", ['file_type' => $fileType]);
            
            $finalPath = "{$fileType}s/" . date('Y/m/d') . '/' . $finalFilename;
            Log::info("📁 Chemin final", ['final_path' => $finalPath]);

            // Créer le répertoire s'il n'existe pas
            $finalDir = dirname(storage_path("app/media/" . $finalPath));
            if (!is_dir($finalDir)) {
                mkdir($finalDir, 0777, true);
            }
            $finalHandle = fopen(storage_path("app/media/" . $finalPath), 'wb');
            if (!$finalHandle) {
                throw new \Exception("Impossible de créer le fichier final: {$finalPath}");
            }

            $totalSize = 0;
            foreach ($chunks as $chunkFile) {
                $chunkData = file_get_contents($chunkFile);
                if ($chunkData === false) {
                    fclose($finalHandle);
                    throw new \Exception("Impossible de lire le chunk: {$chunkFile}");
                }
                fwrite($finalHandle, $chunkData);
                $totalSize += strlen($chunkData);
            }
            fclose($finalHandle);

            // Nettoyer les chunks temporaires
            foreach ($chunks as $chunkFile) {
                @unlink($chunkFile);
            }
            @rmdir($chunkDir);

            // Générer l'URL
            $fileUrl = $this->generateUrlForFileType($fileType, $finalPath);

            // Vérifier que le chemin est valide
            if (empty($finalPath) || $finalPath === '0' || !is_string($finalPath)) {
                Log::error("❌ Chemin invalide", [
                    'final_path' => $finalPath,
                    'file_type' => $fileType,
                    'final_filename' => $finalFilename,
                ]);
                throw new \Exception("Chemin de fichier invalide: {$finalPath}");
            }
            
            Log::info("💾 Création MediaFile", [
                'file_path' => $finalPath,
                'file_type' => $fileType,
                'file_size' => $totalSize,
            ]);
            
            // Extraire la durée pour les fichiers audio/vidéo
            $durationSeconds = null;
            if (in_array($fileType, ['video', 'audio'], true)) {
                try {
                    // S'assurer que le fichier est complètement écrit sur le disque
                    $absolutePath = storage_path("app/media/" . $finalPath);
                    
                    // Vérifier que le fichier existe et a une taille > 0
                    if (!file_exists($absolutePath)) {
                        Log::warning("⚠️ Fichier non trouvé pour extraction durée (chunks)", [
                            'final_path' => $finalPath,
                            'absolute_path' => $absolutePath
                        ]);
                    } else {
                        // Attendre un peu pour s'assurer que le fichier est complètement écrit
                        $maxWait = 5; // 5 secondes max
                        $waitTime = 0;
                        while ($waitTime < $maxWait && !is_readable($absolutePath)) {
                            usleep(100000); // 0.1 secondes
                            $waitTime += 0.1;
                        }
                        
                        Log::info("🎬 Extraction durée pour (chunks): {$finalPath}", [
                            'absolute_path' => $absolutePath,
                            'file_exists' => file_exists($absolutePath),
                            'file_size' => file_exists($absolutePath) ? filesize($absolutePath) : 0,
                            'is_readable' => is_readable($absolutePath),
                        ]);
                        
                        $durationFloat = $this->metadataService->getDurationInSeconds($finalPath);
                        if ($durationFloat !== null) {
                            // Convertir en entier (secondes) pour le stockage (le cast est integer)
                            $durationSeconds = (int) round($durationFloat);
                            Log::info("✅ Durée extraite (chunks): {$durationFloat} secondes (arrondi: {$durationSeconds}s)");
                        } else {
                            Log::warning("⚠️ Durée non extraite (chunks) - ffprobe a retourné null", [
                                'final_path' => $finalPath,
                                'absolute_path' => $absolutePath
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("⚠️ Échec extraction durée (chunks) - non bloquant", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'final_path' => $finalPath ?? null
                    ]);
                    // Ne pas bloquer l'upload si l'extraction de durée échoue
                    $durationSeconds = null;
                }
            }

            // Créer l'entrée MediaFile
            $mediaFile = MediaFile::create([
                'filename' => $finalFilename,
                'original_name' => $filename,
                'file_path' => (string)$finalPath, // S'assurer que c'est une chaîne
                'file_url' => $fileUrl,
                'file_type' => $fileType,
                'mime_type' => $mimeType,
                'file_size' => $totalSize,
                'duration' => $durationSeconds,
                'file_size_formatted' => $this->formatBytes($totalSize),
                'status' => 'importing',
                'progress' => 0,
                'bytes_total' => $totalSize,
                'bytes_uploaded' => $totalSize,
            ]);
            
            Log::info("✅ MediaFile créé", [
                'id' => $mediaFile->id,
                'file_path' => $mediaFile->file_path,
            ]);

            $sessionFile = UploadSessionFile::createForSession($session, $mediaFile);

            // Lancer le traitement
            ProcessMediaFile::dispatch($mediaFile);

            // Déclenchement automatique de la pré-conversion HLS pour les vidéos (non-bloquant)
            try {
                $mediaId = $mediaFile->id;
                if ($fileType === 'video') {
                    register_shutdown_function(function () use ($mediaId) {
                        try {
                            $mf = \App\Models\MediaFile::find($mediaId);
                            if ($mf && $mf->file_type === 'video') {
                                $svc = new \App\Services\AntMediaVoDService();
                                $res = $svc->createVodForMediaFile($mf);
                                \Illuminate\Support\Facades\Log::info('🎬 Pré-conversion HLS (chunks) post-finalize', [
                                    'media_file_id' => $mediaId,
                                    'success' => $res['success'] ?? null,
                                    'message' => $res['message'] ?? null,
                                ]);
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning('⚠️ Pré-conversion HLS (chunks) échouée: ' . $e->getMessage(), [
                                'media_file_id' => $mediaId
                            ]);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::warning('⚠️ Impossible d’enregistrer la pré-conversion (chunks): ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès',
                'data' => [
                    'media_file' => $mediaFile,
                    'session_file' => $sessionFile,
                ]
            ], 201);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur finalisation chunks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation de l\'upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Annuler un upload en cours (chunks)
     * 
     * Supprime tous les chunks temporaires d'un upload en cours
     */
    public function cancelChunkUpload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'upload_id' => 'required|string',
                'session_token' => 'nullable|string|exists:upload_sessions,session_token',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadId = $request->upload_id;
            $sessionToken = $request->session_token;

            // Optionnel: valider la session si fournie
            if ($sessionToken) {
                $session = \App\Models\UploadSession::where('session_token', $sessionToken)->first();
                if (!$session) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Session invalide'
                    ], 401);
                }
            }

            // Vérifier que le répertoire chunks existe
            $chunkDir = storage_path("app/chunks/{$uploadId}");
            if (!is_dir($chunkDir)) {
                return response()->json([
                    'success' => false,
                    'message' => "Aucun upload en cours trouvé pour cet upload_id: {$uploadId}"
                ], 404);
            }

            // Calculer la taille avant suppression (pour log)
            $totalSize = 0;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($chunkDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $totalSize += $file->getSize();
                }
            }

            // Supprimer le répertoire et tous ses contenus
            $this->deleteDirectory($chunkDir);

            Log::info("🗑️ Upload annulé", [
                'upload_id' => $uploadId,
                'session_token' => $sessionToken,
                'size_freed' => $totalSize,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Upload annulé avec succès',
                'data' => [
                    'upload_id' => $uploadId,
                    'size_freed' => $totalSize,
                    'size_freed_formatted' => $this->formatBytes($totalSize),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur annulation upload chunks: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation de l\'upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer récursivement un répertoire
     */
    private function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $files = array_diff(scandir($directory), ['.', '..']);

        foreach ($files as $file) {
            $path = $directory . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($directory);
    }

    /**
     * Récupère le statut de conversion HLS de tous les fichiers vidéo
     * Triés du plus récent au plus ancien
     * 
     * @return JsonResponse
     */
    public function getConversionStatus(): JsonResponse
    {
        try {
            $vodService = new AntMediaVoDService();
            $streamsBase = '/usr/local/antmedia/webapps/LiveApp/streams';
            
            // Récupérer tous les fichiers vidéo triés du plus récent au plus ancien
            $videoFiles = MediaFile::where('file_type', 'video')
                ->whereNotNull('file_path')
                ->orderBy('created_at', 'desc')
                ->get();

            $results = [];
            
            foreach ($videoFiles as $mediaFile) {
                $vodName = $vodService->buildVodNameForMediaFile($mediaFile->id);
                $vodDir = $streamsBase . '/' . $vodName;
                $playlistPath = $vodDir . '/playlist.m3u8';
                
                // Vérifier si le fichier source existe (même résolution que le disque 'media')
                $sourcePath = Storage::disk('media')->path($mediaFile->file_path);
                $sourceExists = file_exists($sourcePath);
                
                // Déterminer le statut de conversion
                $conversionStatus = 'unknown';
                $hlsExists = false;
                $hlsComplete = false;
                $streamUrl = null;
                $segmentCount = 0;
                
                if (!$sourceExists) {
                    $conversionStatus = 'source_missing';
                } elseif (!file_exists($playlistPath)) {
                    $conversionStatus = 'not_started';
                } else {
                    $hlsExists = true;
                    $hlsComplete = $vodService->isVodComplete($vodName);
                    
                    if ($hlsComplete) {
                        $conversionStatus = 'completed';
                        $streamUrl = $vodService->buildHlsUrlFromVodName($vodName);
                        // Compter les segments
                        $segments = glob($vodDir . '/segment_*.ts') ?: [];
                        $segmentCount = count($segments);
                    } else {
                        $conversionStatus = 'incomplete';
                        // Compter les segments existants même si incomplet
                        $segments = glob($vodDir . '/segment_*.ts') ?: [];
                        $segmentCount = count($segments);
                    }
                }
                
                // Vérifier si une conversion est en cours (verrou)
                // On vérifie directement dans la table cache_locks sans acquérir le verrou
                // pour éviter toute interférence avec les conversions en cours
                $lockKey = 'vod-conversion-lock:' . $mediaFile->id;
                $lockExists = false;
                
                try {
                    // Vérifier si le verrou existe dans la base de données
                    // On lit directement la table cache_locks sans acquérir le verrou
                    // pour éviter toute interférence avec les conversions en cours
                    $lockRecord = DB::table('cache_locks')
                        ->where('key', $lockKey)
                        ->where('expiration', '>', time())
                        ->first();
                    
                    $lockExists = $lockRecord !== null;
                } catch (\Exception $e) {
                    // Si la table n'existe pas ou erreur, on essaie avec la méthode lock (fallback)
                    // On utilise block(0) pour un essai non-bloquant
                    $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 3600);
                    $acquired = $lock->block(0);
                    $lockExists = !$acquired;
                    if ($acquired) {
                        $lock->release();
                    }
                }
                
                if ($lockExists && $conversionStatus !== 'completed') {
                    // Le verrou existe, donc une conversion est en cours
                    $conversionStatus = 'in_progress';
                }
                
                $results[] = [
                    'media_file_id' => $mediaFile->id,
                    'filename' => $mediaFile->filename,
                    'original_name' => $mediaFile->original_name,
                    'file_size' => $mediaFile->file_size,
                    'file_size_formatted' => $mediaFile->file_size_formatted,
                    'duration' => $mediaFile->duration,
                    'created_at' => $mediaFile->created_at?->toIso8601String(),
                    'updated_at' => $mediaFile->updated_at?->toIso8601String(),
                    'conversion_status' => $conversionStatus,
                    'hls_exists' => $hlsExists,
                    'hls_complete' => $hlsComplete,
                    'stream_url' => $streamUrl,
                    'segment_count' => $segmentCount,
                    'vod_name' => $vodName,
                    'source_exists' => $sourceExists,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut de conversion récupéré avec succès',
                'data' => $results,
                'total' => count($results),
                'summary' => [
                    'completed' => count(array_filter($results, fn($r) => $r['conversion_status'] === 'completed')),
                    'in_progress' => count(array_filter($results, fn($r) => $r['conversion_status'] === 'in_progress')),
                    'incomplete' => count(array_filter($results, fn($r) => $r['conversion_status'] === 'incomplete')),
                    'not_started' => count(array_filter($results, fn($r) => $r['conversion_status'] === 'not_started')),
                    'source_missing' => count(array_filter($results, fn($r) => $r['conversion_status'] === 'source_missing')),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération statut conversion: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut de conversion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
