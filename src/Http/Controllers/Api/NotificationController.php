<?php

namespace WhatsApp\Business\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use WhatsApp\Business\Models\Notification;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $agent = $request->user();

        $query = Notification::where('agent_id', $agent->id)
            ->orWhereNull('agent_id')
            ->where('customer_id', $agent->customer_id);

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $unreadCount = Notification::where('agent_id', $agent->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();

        $notification = Notification::where('id', $id)
            ->where(function ($query) use ($agent) {
                $query->where('agent_id', $agent->id)
                    ->orWhereNull('agent_id');
            })
            ->where('customer_id', $agent->customer_id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $agent = $request->user();

        Notification::where('agent_id', $agent->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $agent = $request->user();

        $notification = Notification::where('id', $id)
            ->where('customer_id', $agent->customer_id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }
}
