<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AzuraCastApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AzuraCastController extends Controller
{
    public function getPlaylists(): JsonResponse
    {
        $apiService = new AzuraCastApiService();
        $result = $apiService->getPlaylists();

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }

    public function createPlaylist(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'media_ids' => 'array',
            'media_ids.*' => 'integer',
            'loop' => 'boolean',
            'shuffle' => 'boolean'
        ]);

        $apiService = new AzuraCastApiService();
        $result = $apiService->createPlaylist(
            $request->name,
            $request->media_ids ?? [],
            $request->loop ?? false,
            $request->shuffle ?? false
        );

        if ($result['success']) {
            return response()->json($result, 201);
        } else {
            return response()->json($result, 500);
        }
    }

    public function addMediaToPlaylist(Request $request, int $playlist): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer'
        ]);

        $apiService = new AzuraCastApiService();
        $result = $apiService->addMediaToPlaylist($playlist, $request->media_id);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 500);
        }
    }
}
