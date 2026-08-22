<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * In-app notification centre for the authenticated user.
 *
 * Every query and mutation is scoped server-side to auth()->user(); the
 * client never supplies a user id. Notifications belonging to other users are
 * indistinguishable from nonexistent ones (404), so ids cannot be probed.
 */
class NotificationController extends Controller
{
    /**
     * Paginated notification list, newest first.
     *
     * Query: ?unread=1 to list only unread, ?per_page=10..100 (default 15).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max(1, $request->integer('per_page', 15)), 100);

        $query = $request->user()->notifications()->orderByDesc('created_at')->orderByDesc('id');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        return NotificationResource::collection(
            $query->paginate($perPage)->withQueryString(),
        );
    }

    /**
     * Efficient unread count for the badge — a single indexed COUNT query,
     * never a full fetch into PHP.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark one of the user's own notifications as read. Read notifications
     * stay in the history; nothing is deleted here.
     */
    public function markRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $this->authorizeOwned($request, $notification);

        $notification->markAsRead();

        return response()->json(['data' => new NotificationResource($notification)]);
    }

    /**
     * Mark all of the user's unread notifications as read in one scoped
     * UPDATE. Other users' rows are never touched.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['count' => 0]);
    }

    /**
     * Delete one of the user's own notifications (e.g. dismiss from centre).
     */
    public function destroy(Request $request, DatabaseNotification $notification): Response
    {
        $this->authorizeOwned($request, $notification);

        $notification->delete();

        return response()->noContent();
    }

    /**
     * Ownership is enforced by comparing the stored notifiable against the
     * authenticated user. A mismatch yields 404 rather than 403 so foreign
     * notification ids cannot be distinguished from invalid ones.
     */
    private function authorizeOwned(Request $request, DatabaseNotification $notification): void
    {
        $user = $request->user();

        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
                && $notification->notifiable_id === $user->getKey(),
            404,
            'Notification not found.',
        );
    }
}
