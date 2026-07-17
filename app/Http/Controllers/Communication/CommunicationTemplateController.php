<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreCommunicationTemplateRequest;
use App\Http\Requests\Communication\UpdateCommunicationTemplateRequest;
use App\Models\CommunicationTemplate;
use App\Support\CommunicationOrganizationResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommunicationTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CommunicationTemplate::class);

        $templates = CommunicationTemplate::query()
            ->with(['creator'])
            ->latest()
            ->get();

        return view('pages.communications.templates.index', [
            'title' => 'Communication Templates',
            'templates' => $templates,
            'channels' => CommunicationTemplate::channels(),
            'strategies' => CommunicationTemplate::recipientStrategies(),
            'variables' => config('communications.template_variables'),
        ]);
    }

    public function store(StoreCommunicationTemplateRequest $request): RedirectResponse
    {
        CommunicationTemplate::create([
            ...$request->validated(),
            'organization_id' => CommunicationOrganizationResolver::resolve($request->user()),
            'slug' => $request->input('slug') ?: Str::slug($request->input('name')),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('communications.templates.index')
            ->with('success', 'Communication template created.');
    }

    public function update(UpdateCommunicationTemplateRequest $request, CommunicationTemplate $template): RedirectResponse
    {
        $template->update([
            ...$request->validated(),
            'slug' => $request->input('slug') ?: Str::slug($request->input('name')),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('communications.templates.index')
            ->with('success', 'Communication template updated.');
    }

    public function destroy(CommunicationTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $template->delete();

        return redirect()
            ->route('communications.templates.index')
            ->with('success', 'Communication template deleted.');
    }

    public function toggle(CommunicationTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $template->update([
            'is_active' => ! $template->is_active,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('communications.templates.index')
            ->with('success', 'Template status updated.');
    }
}
