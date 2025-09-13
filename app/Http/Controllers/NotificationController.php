<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    /**
     * Format a notification model into array.
     */
    protected function formatNotification($notification)
    {
        $data = $notification->data ?? [];

        return [
            'id'         => $notification->id,
            'type'       => class_basename($notification->type),
            'title'      => data_get($data, 'title'),
            'excerpt'    => data_get($data, 'excerpt'),
            'priority'   => data_get($data, 'priority'),
            'url'        => data_get($data, 'url'),
            'read_at'    => optional($notification->read_at)?->toISOString(),
            'created_at' => $notification->created_at->toISOString(),
        ];
    }

    public function index(Request $request)
    {
        $request->validate([
            'only'     => ['sometimes', Rule::in(['all', 'unread'])],
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort'     => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $user   = $request->user();
        $only   = $request->query('only', 'all');
        $sort   = $request->query('sort', 'desc');
        $per    = (int) $request->query('per_page', 15);

        $query  = $only === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        $paginator = $query->orderBy('created_at', $sort)->paginate($per);

        return response()->json([
            'success' => true,
            'data'    => collect($paginator->items())->map(fn($n) => $this->formatNotification($n)),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'count'   => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, $id)
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data'    => $this->formatNotification($notification->fresh()),
        ]);
    }

    public function markManyRead(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $user = $request->user();
        $updated = $user->notifications()
            ->whereIn('id', $validated['ids'])
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
            'updated' => $updated,
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $deleted = $request->user()
            ->notifications()
            ->where('id', $id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }

    public function clear(Request $request)
    {
        $request->validate([
            'only' => ['sometimes', Rule::in(['all', 'unread'])],
        ]);

        $only = $request->query('only', 'all');
        $query = $only === 'unread'
            ? $request->user()->unreadNotifications()
            : $request->user()->notifications();

        $count = $query->count();
        $query->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications cleared.',
            'deleted' => $count,
        ]);
    }
}