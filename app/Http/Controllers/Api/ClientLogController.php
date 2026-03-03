<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientLogController extends Controller
{
    public function log(Request $request)
    {
        $level = $request->input('level', 'info');
        $message = $request->input('message');
        $context = $request->input('context', []);
        
        // Ajouter l'IP pour corrélation
        $context['ip'] = $request->ip();
        $context['ua'] = $request->userAgent();

        Log::channel('client_debug')->log($level, "CLIENT: $message", $context);

        return response()->json(['success' => true]);
    }
}
