<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaLibraryController extends Controller
{
    public function getStats(): JsonResponse
    {
        try {
            $totalFiles = DB::table("media_files")->count();
            
            $videos = DB::table("media_files")
                ->where("mime_type", "like", "video%")
                ->count();
                
            $audios = DB::table("media_files")
                ->where("mime_type", "like", "audio%")
                ->count();
                
            $images = DB::table("media_files")
                ->where("mime_type", "like", "image%")
                ->count();
            
            return response()->json([
                "success" => true,
                "data" => [
                    "total_files" => $totalFiles,
                    "videos" => $videos,
                    "audios" => $audios,
                    "images" => $images
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("MediaLibraryController: " . $e->getMessage());
            
            return response()->json([
                "success" => false,
                "message" => "Erreur",
                "error" => $e->getMessage()
            ], 500);
        }
    }
}
