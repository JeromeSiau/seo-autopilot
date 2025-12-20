<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    public function index()
    {
        $user = auth()->user();

        return response()->json([
            'notifications' => $user->notifications()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'unread_count' => $user->unreadNotificationsCount(),
        ]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        $this->notifications->markAllAsRead(auth()->user());

        return response()->json(['success' => true]);
    }
}
