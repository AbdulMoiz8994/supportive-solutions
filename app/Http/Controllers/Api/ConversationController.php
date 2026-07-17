<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ConversationMessageResource;
use App\Http\Resources\Api\ConversationResource;
use App\Models\SecureMessageParticipant;
use App\Models\SecureMessageThread;
use App\Services\Communication\SecureMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Chat / messaging for the mobile app. Backed by the same secure-message
 * threads the web app uses, scoped to conversations the logged-in user
 * is a participant in.
 */
class ConversationController extends Controller
{
    public function __construct(
        protected SecureMessageService $secureMessageService
    ) {}

    /**
     * Inbox: the user's conversations, newest activity first.
     * Query: ?unread=1, ?search=text, ?per_page=20.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $perPage = min((int) $request->integer('per_page', 20) ?: 20, 100);

        $threads = SecureMessageThread::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['participants.user.employee', 'latestMessage.sender', 'creator.employee'])
            ->when($request->boolean('unread'), function ($q) use ($user) {
                $q->whereHas('participants', fn ($p) => $p->where('user_id', $user->id)->whereNull('last_read_at'));
            })
            ->when(trim((string) $request->query('search')) !== '', function ($q) use ($request) {
                $q->where('subject', 'like', '%'.trim((string) $request->query('search')).'%');
            })
            ->latest('last_message_at')
            ->paginate($perPage);

        return ConversationResource::collection($threads);
    }

    /**
     * Number of conversations with unread messages (inbox badge).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = SecureMessageParticipant::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('last_read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Open a conversation: its messages oldest-first, and mark it read.
     */
    public function show(Request $request, SecureMessageThread $thread): JsonResponse
    {
        $user = $request->user();
        $this->assertParticipant($thread, $user);

        $this->secureMessageService->markThreadRead($thread, $user);

        $thread->load(['participants.user.employee', 'creator.employee']);
        $messages = $thread->messages()
            ->with('sender.employee')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => [
                'id'          => $thread->id,
                'subject'     => $thread->subject,
                'participants' => $thread->participants->map(fn (SecureMessageParticipant $p) => [
                    'id'   => $p->user_id,
                    'name' => $p->user?->name,
                ])->values(),
                'messages' => ConversationMessageResource::collection($messages)->toArray($request),
            ],
        ]);
    }

    /**
     * Send a message into an existing conversation.
     */
    public function sendMessage(Request $request, SecureMessageThread $thread): JsonResponse
    {
        $this->assertParticipant($thread, $request->user());

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->secureMessageService->reply($thread, $request->user(), $data['body']);
        $message->loadMissing('sender.employee');

        return response()->json([
            'message' => 'Message sent.',
            'data'    => (new ConversationMessageResource($message))->toArray($request),
        ], 201);
    }

    /**
     * Start a new conversation with one or more users in the organization.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject'          => ['required', 'string', 'max:255'],
            'body'             => ['required', 'string', 'max:5000'],
            'participant_ids'  => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer'],
        ]);

        $thread = $this->secureMessageService->createThread(
            $request->user(),
            $data['subject'],
            $data['body'],
            $data['participant_ids'],
        );

        $thread->load(['participants.user.employee', 'latestMessage.sender', 'creator.employee']);

        return response()->json([
            'message' => 'Conversation started.',
            'data'    => (new ConversationResource($thread))->toArray($request),
        ], 201);
    }

    /**
     * The mobile app is a personal inbox: access is limited to conversations
     * the user actually belongs to, regardless of the org-wide
     * `manage_secure_messages` permission the web console relies on.
     */
    private function assertParticipant(SecureMessageThread $thread, \App\Models\User $user): void
    {
        $isParticipant = SecureMessageParticipant::query()
            ->where('thread_id', $thread->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($isParticipant, 403);
    }
}
