<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\ReplySecureMessageRequest;
use App\Http\Requests\Communication\StoreSecureMessageThreadRequest;
use App\Models\Client;
use App\Models\Employee;
use App\Models\SecureMessageThread;
use App\Models\User;
use App\Services\Communication\SecureMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecureMessageThreadController extends Controller
{
    public function __construct(
        protected SecureMessageService $secureMessageService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SecureMessageThread::class);

        $user = $request->user();
        $query = SecureMessageThread::query()
            ->with(['participants.user', 'creator'])
            ->latest('last_message_at');

        if (! $user->hasPermission('manage_secure_messages')) {
            $query->whereHas('participants', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($request->boolean('unread')) {
            $query->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)->whereNull('last_read_at');
            });
        }

        if ($search = trim($request->string('search')->toString())) {
            $query->where('subject', 'like', "%{$search}%");
        }

        $threads = $query->paginate(20)->withQueryString();

        $users = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('id', '!=', $user->id)
            ->orderBy('name')
            ->get();

        return view('pages.communications.secure-messages.index', [
            'title' => 'Secure Messages',
            'threads' => $threads,
            'users' => $users,
            'filters' => $request->only(['search', 'unread']),
        ]);
    }

    public function show(SecureMessageThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load(['messages.sender', 'participants.user', 'related']);
        $this->secureMessageService->markThreadRead($thread, auth()->user());

        return view('pages.communications.secure-messages.show', [
            'title' => $thread->subject,
            'thread' => $thread,
        ]);
    }

    public function store(StoreSecureMessageThreadRequest $request): RedirectResponse
    {
        $relatedClient = null;
        $relatedEmployee = null;

        if ($request->filled('related_type') && $request->filled('related_id')) {
            if ($request->input('related_type') === 'Client') {
                $relatedClient = Client::findOrFail($request->integer('related_id'));
            } else {
                $relatedEmployee = Employee::findOrFail($request->integer('related_id'));
            }
        }

        $thread = $this->secureMessageService->createThread(
            $request->user(),
            $request->input('subject'),
            $request->input('body'),
            $request->input('participant_ids', []),
            $relatedClient,
            $relatedEmployee
        );

        return redirect()
            ->route('communications.secure-messages.show', $thread)
            ->with('success', 'Secure message thread created.');
    }

    public function reply(ReplySecureMessageRequest $request, SecureMessageThread $thread): RedirectResponse
    {
        $this->secureMessageService->reply($thread, $request->user(), $request->input('body'));

        return redirect()
            ->route('communications.secure-messages.show', $thread)
            ->with('success', 'Reply sent.');
    }
}
