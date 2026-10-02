<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

/**
 * In-app notifications for the authenticated user. Backed by the same
 * user_notifications table the rest of the app writes to (booking updates,
 * offers, etc.), so the app inbox stays in sync with server-side events.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = $request->user()->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->integer('per_page', 20) ?: 20)
            ->through(fn (UserNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'icon' => $n->icon,
                'data' => $n->data,
                'read' => (bool) $n->read_at,
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return $this->ok([
            'notifications' => $notifications,
            'unread_count' => $request->user()->unreadNotificationsCount(),
        ]);
    }

    public function unreadCount(Request $request)
    {
        return $this->ok(['unread_count' => $request->user()->unreadNotificationsCount()]);
    }

    public function markRead(Request $request, int $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();
        abort_unless($notification, 404);

        $notification->forceFill(['read_at' => now()])->save();

        return $this->ok(['unread_count' => $request->user()->unreadNotificationsCount()], 'Marked as read.');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return $this->ok(['unread_count' => 0], 'All notifications marked as read.');
    }
}
