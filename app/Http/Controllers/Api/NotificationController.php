<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\NotificationResource;
use App\Models\CommunicationNotification;
use App\Services\Communication\CommunicationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Push/in-app notifications for the logged-in mobile user.
 * Backed by the same CommunicationNotification store as the web app,
 * so schedule updates, compliance alerts and new-message pings all appear here.
 */
class NotificationController extends Controller
{
    public function __construct(
        protected CommunicationNotificationService $notificationService
    ) {}

    /**
     * Paginated notification feed (newest first). Query: ?unread=1, ?per_page=25.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 25) ?: 25, 100);

        $notifications = CommunicationNotification::query()
            ->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * Badge count for unread notifications ("2 New Alerts").
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    /**
     * Mark a single notification read.
     */
    public function markRead(Request $request, CommunicationNotification $notification): JsonResponse
    {
        $this->authorizeOwnership($request, $notification);

        $this->notificationService->markAsRead($notification, $request->user());

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark every notification read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user());

        return response()->json([
            'message' => 'All notifications marked as read.',
            'updated' => $count,
        ]);
    }

    /**
     * Delete a notification (swipe-to-delete in the app).
     */
    public function destroy(Request $request, CommunicationNotification $notification): JsonResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    private function authorizeOwnership(Request $request, CommunicationNotification $notification): void
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
    }
}
