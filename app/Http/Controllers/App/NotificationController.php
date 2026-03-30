<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $notifications = $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->latest()
            ->limit(50)
            ->get();

        $unreadCount = $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function archiveAll(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $request->user()
            ->notifications()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->update(['archived_at' => now()]);

        return response()->json(['success' => true]);
    }
}
