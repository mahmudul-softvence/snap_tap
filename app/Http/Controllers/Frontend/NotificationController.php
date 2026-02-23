<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(20)
            ->through(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'is_read' => $n->read_at != null,
                'read_at' => $n->read_at,
                'time' => $n->created_at->diffForHumans(),
            ]);

        if ($notifications->isEmpty()) {
            return response()->json([
                'message' => 'No notifications found',
                'unread_count' => 0,
                'notifications' => [],
            ]);
        }

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function viewAll(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'is_read' => $n->read_at != null,
                'read_at' => $n->read_at,
                'time' => $n->created_at->diffForHumans(),
            ]);

        if ($notifications->isEmpty()) {
            return response()->json([
                'message' => 'No notifications found',
                'unread_count' => 0,
                'notifications' => [],
            ]);
        }

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }


    //Mark single notification as read
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }

    //Mark all notifications as read
    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->unreadNotifications
            ->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }
}
