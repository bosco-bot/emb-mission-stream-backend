<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RadioSource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RadioSourceController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $sources = RadioSource::orderBy('created_at', 'desc')->get();
            return response()->json([
                'success' => true,
                'message' => 'Sources récupérées avec succès',
                'data' => $sources
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des sources',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $source = RadioSource::find($id);
            if (!$source) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source non trouvée',
                    'data' => null
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'Source récupérée avec succès',
                'data' => $source
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la source',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'url' => 'required|url|max:500',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }
            $source = RadioSource::create([
                'name' => $request->name,
                'url' => $request->url,
                'is_active' => true,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Source créée avec succès',
                'data' => $source
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la source',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $source = RadioSource::find($id);
            if (!$source) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source non trouvée',
                    'data' => null
                ], 404);
            }
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'url' => 'sometimes|url|max:500',
                'is_active' => 'sometimes|boolean',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données de validation invalides',
                    'errors' => $validator->errors()
                ], 422);
            }
            $source->update($request->only(['name', 'url', 'is_active']));
            return response()->json([
                'success' => true,
                'message' => 'Source mise à jour avec succès',
                'data' => $source
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la source',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $source = RadioSource::find($id);
            if (!$source) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source non trouvée',
                    'data' => null
                ], 404);
            }
            $source->delete();
            return response()->json([
                'success' => true,
                'message' => 'Source supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la source',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

