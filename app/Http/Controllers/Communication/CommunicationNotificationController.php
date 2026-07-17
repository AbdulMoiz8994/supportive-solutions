<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\CommunicationAttachment;
use App\Models\CommunicationNotification;
use App\Services\Communication\CommunicationAttachmentService;
use App\Services\Communication\CommunicationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunicationNotificationController extends Controller
{
    public function __construct(
        protected CommunicationNotificationService $notificationService,
        protected CommunicationAttachmentService $attachmentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CommunicationNotification::class);

        $notifications = CommunicationNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(25);

        return view('pages.communications.notifications.index', [
            'title' => 'Notifications',
            'notifications' => $notifications,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationNotification::class);

        return response()->json([
            'count' => $this->notificationService->unreadCount($request->user()),
        ]);
    }

    public function recent(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CommunicationNotification::class);

        $items = CommunicationNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (CommunicationNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'count' => $this->notificationService->unreadCount($request->user()),
            'items' => $items,
        ]);
    }

    public function markRead(CommunicationNotification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);

        $this->notificationService->markAsRead($notification, auth()->user());

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', CommunicationNotification::class);

        $this->notificationService->markAllAsRead($request->user());

        return back()->with('success', 'All notifications marked as read.');
    }

    public function downloadAttachment(CommunicationAttachment $attachment): StreamedResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && (int) $attachment->organization_id !== (int) $user->organization_id) {
            abort(403);
        }

        $communication = $attachment->communication;
        if ($communication) {
            $this->authorize('view', $communication);
        }

        return $this->attachmentService->download($attachment, $user);
    }
}
