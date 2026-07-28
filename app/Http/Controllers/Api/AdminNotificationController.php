<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = AdminNotification::query()
            ->unread()
            ->latest()
            ->limit(10)
            ->get(['id', 'type', 'headline', 'tone', 'payload', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function markRead(AdminNotification $notification): JsonResponse
    {
        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
