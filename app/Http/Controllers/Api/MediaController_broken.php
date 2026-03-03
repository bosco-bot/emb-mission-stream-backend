<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\UploadSession;
use App\Models\MediaFileRelation;
use App\Models\UploadSessionFile;
use Illuminate\Http\Request;
use App\Jobs\ProcessMediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MediaController extends Controller
{
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
        try {
                'message' => 'Erreur lors de la récupération des fichiers',
                'error' => $e->getMessage()
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
                        'file_url' => $this->generateFileUrl($file), // URL mise à jour
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
                        'file_url' => $this->generateFileUrl($file), // URL mise à jour
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
                        'file_url' => $this->generateFileUrl($file), // URL mise à jour
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
            
            // Mettre à jour l'URL du fichier
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

            // Supprimer le fichier physique s'il existe
            if (Storage::exists($mediaFile->file_path)) {
                Storage::delete($mediaFile->file_path);
            }

            // Supprimer de la base de données
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

            // Réinitialiser le statut
            $mediaFile->update([
                'status' => 'importing',
                'progress' => 0,
                'error_message' => null,
                'bytes_uploaded' => 0,
            ]);

            // Mettre à jour l'URL
            $mediaFile->file_url = $this->generateFileUrl($mediaFile);
            
            ProcessMediaFile::dispatch($mediaFile);
            
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

    /**
     * Supprimer un fichier
     */
    public function destroy(MediaFile $mediaFile): JsonResponse
    {
        try {
            // Supprimer le fichier physique
            if (Storage::exists($mediaFile->file_path)) {
                Storage::delete($mediaFile->file_path);
            }

            // Supprimer le thumbnail s'il existe
            if ($mediaFile->thumbnail_path && Storage::exists($mediaFile->thumbnail_path)) {
                Storage::delete($mediaFile->thumbnail_path);
            }

            // Supprimer de la base de données (cascade sur les relations)
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
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:50000', // 50MB max
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

            // Déterminer le type de fichier
            $fileType = $this->determineFileType($file->getMimeType());
            
            // Générer un nom unique
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $filePath = "media/{$fileType}s/" . date('Y/m/d') . '/' . $filename;

            // Stocker le fichier
            $storedPath = $file->storeAs("media/{$fileType}s/" . date('Y/m/d'), $filename, "media");

            // Générer l'URL selon le type de fichier
            $fileUrl = $this->generateUrlForFileType($fileType, $storedPath);

            // Créer l'enregistrement en base
            $mediaFile = MediaFile::create([
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $storedPath,
                'file_url' => $fileUrl,
                'file_type' => $fileType,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_size_formatted' => $this->formatBytes($file->getSize()),
                'status' => 'importing',
                'progress' => 0,
                'bytes_total' => $file->getSize(),
                'bytes_uploaded' => $file->getSize(),
            ]);

            // Ajouter à la session
            $sessionFile = UploadSessionFile::createForSession($session, $mediaFile);

            ProcessMediaFile::dispatch($mediaFile);

            return response()->json([
                'success' => true,
                'message' => 'Fichier uploadé avec succès',
                'data' => [
                    'media_file' => $mediaFile,
                    'session_file' => $sessionFile,
                ]
            ], 201);
            
        } catch (\Exception $e) {
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
                'files.*' => 'file|max:50000',
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

            foreach ($files as $index => $file) {
                // Déterminer le type de fichier
                $fileType = $this->determineFileType($file->getMimeType());
                
                // Générer un nom unique
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $filePath = "media/{$fileType}s/" . date('Y/m/d') . '/' . $filename;

                // Stocker le fichier
                $storedPath = $file->storeAs("media/{$fileType}s/" . date('Y/m/d'), $filename, "media");

                // Générer l'URL selon le type de fichier
                $fileUrl = $this->generateUrlForFileType($fileType, $storedPath);

                // Créer l'enregistrement en base
                $mediaFile = MediaFile::create([
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'file_url' => $fileUrl,
                    'file_type' => $fileType,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_size_formatted' => $this->formatBytes($file->getSize()),
                    'status' => 'importing',
                    'progress' => 0,
                    'bytes_total' => $file->getSize(),
                    'bytes_uploaded' => $file->getSize(),
                ]);

                // Ajouter à la session
                $sessionFile = UploadSessionFile::createForSession($session, $mediaFile, $index + 1);
                
                $uploadedFiles[] = [
                    'media_file' => $mediaFile,
                    'session_file' => $sessionFile,
                ];
            }

            // Mettre à jour la session
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
            'video' => 'https://tv.embmission.com/storage/',
            'audio' => 'https://radio.embmission.com/storage/',
            'image' => 'https://radio.embmission.com/storage/',
            default => 'https://radio.embmission.com/storage/',
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

        // Les thumbnails sont toujours sur radio.embmission.com
        return 'https://radio.embmission.com/storage/' . $file->thumbnail_path;
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
