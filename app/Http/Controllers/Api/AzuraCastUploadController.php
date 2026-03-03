<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AzuraCastUploadService;
use App\Models\MediaFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AzuraCastUploadController extends Controller
{
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'media_file_id' => 'required|integer|exists:media_files,id'
            ]);

            $mediaFile = MediaFile::findOrFail($request->media_file_id);
            $filePath = storage_path('app/media/' . $mediaFile->file_path);

            $uploadService = new AzuraCastUploadService();
            $result = $uploadService->uploadFile($filePath, $mediaFile->filename);

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file to AzuraCast',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addToPlaylist(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'playlist_id' => 'required|integer',
                'media_id' => 'required|integer'
            ]);

            $uploadService = new AzuraCastUploadService();
            $result = $uploadService->addMediaToPlaylist(
                $request->playlist_id,
                $request->media_id
            );

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adding media to AzuraCast playlist',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
