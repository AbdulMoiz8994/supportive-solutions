<?php

namespace App\Http\Controllers;

use App\Http\Requests\RequestTemplate\StoreRequestTemplateRequest;
use App\Http\Requests\RequestTemplate\UpdateRequestTemplateRequest;
use App\Models\Organization;
use App\Models\RequestTemplate;
use Illuminate\Http\Request;

class RequestTemplateController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', RequestTemplate::class);

        $templates = RequestTemplate::query()
            ->with(['creator'])
            ->latest()
            ->get();

        $organizations = auth()->user()->isSuperAdmin()
            ? Organization::orderBy('name')->get()
            : collect();

        return view('pages.request-templates.index', compact('templates', 'organizations'), [
            'title' => 'Request Templates',
        ]);
    }

    public function store(StoreRequestTemplateRequest $request)
    {
        $validated = $request->validated();

        $organizationId = auth()->user()->organization_id
            ?? $validated['organization_id']
            ?? Organization::query()->value('id');

        RequestTemplate::create([
            'organization_id' => $organizationId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'delivery_method' => $validated['delivery_method'],
            'recipient_type' => $validated['recipient_type'],
            'default_recipient_email' => $validated['default_recipient_email'] ?? null,
            'default_recipient_fax' => $validated['default_recipient_fax'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('request-templates.index')->with('success', 'Request template created successfully.');
    }

    public function update(UpdateRequestTemplateRequest $request, $id)
    {
        $template = RequestTemplate::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validated();

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'delivery_method' => $validated['delivery_method'],
            'recipient_type' => $validated['recipient_type'],
            'default_recipient_email' => $validated['default_recipient_email'] ?? null,
            'default_recipient_fax' => $validated['default_recipient_fax'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active', $template->is_active),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('request-templates.index')->with('success', 'Request template updated successfully.');
    }

    public function toggle($id)
    {
        $template = RequestTemplate::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('update', $template);

        $template->update([
            'is_active' => ! $template->is_active,
            'updated_by' => auth()->id(),
        ]);

        $message = $template->is_active
            ? 'Request template activated.'
            : 'Request template deactivated.';

        return redirect()->route('request-templates.index')->with('success', $message);
    }
}
