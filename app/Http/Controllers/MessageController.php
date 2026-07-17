<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\User;

class MessageController extends Controller
{
    public function index()
    {
        $organizationId = auth()->user()->organization_id;
        
        // Fetch all other users in the same organization
        $contacts = User::where('organization_id', $organizationId)
            ->where('id', '!=', auth()->id())
            ->get();

        return view('pages.messages.index', compact('contacts'), ['title' => 'Messaging Portal']);
    }

    public function show($id)
    {
        $receiver = User::findOrFail($id);
        $organizationId = auth()->user()->organization_id;

        // Fetch contacts for the sidebar
        $contacts = User::where('organization_id', $organizationId)
            ->where('id', '!=', auth()->id())
            ->get();

        // Fetch conversation between auth user and the contact
        $messages = Message::where('organization_id', $organizationId)
            ->where(function ($query) use ($id) {
                $query->where('sender_id', auth()->id())->where('receiver_id', $id)
                    ->orWhere('sender_id', $id)->where('receiver_id', auth()->id());
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark as read
        Message::where('sender_id', $id)
            ->where('receiver_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('pages.messages.show', compact('receiver', 'messages', 'contacts'), ['title' => 'Chat with ' . $receiver->first_name]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'receiver_id' => 'required|exists:users,id'
        ]);

        Message::create([
            'organization_id' => auth()->user()->organization_id,
            'sender_id' => auth()->id(),
            'receiver_id' => $request->receiver_id,
            'content' => $request->content,
        ]);

        return redirect()->back();
    }
}
