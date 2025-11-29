<?php

namespace App\Http\Controllers;

use App\Events\NewNotification;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // ✅ Fetch all notifications (no user_id filter)
    public function index()
    {
        $notifications = Notification::orderBy('created_at', 'desc')->get();

        return response()->json($notifications);
    }

    // ✅ Store a new notification (no user-specific restriction)
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string',
            'data' => 'nullable|array',
        ]);
    
        $notification = Notification::create([
            'user_id' => auth()->id(),  // ⭐ FIXED
            'type' => $request->type,
            'data' => $request->data ?? [],
            'is_read' => false,
        ]);
    
        broadcast(new NewNotification($notification));
    
        return response()->json($notification, 201);
    }


    // ✅ Mark one notification as read (by ID)
    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    // ✅ Mark all notifications as read (for everyone)
    public function markAllRead()
    {
        Notification::where('is_read', false)->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    // ✅ Get count of all unread notifications (global)
    public function unreadCount()
    {
        $count = Notification::where('is_read', false)->count();

        return response()->json(['unread' => $count]);
    }
}
