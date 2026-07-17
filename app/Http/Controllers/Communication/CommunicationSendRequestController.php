<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\SendCommunicationRequestRequest;
use App\Models\Client;
use App\Models\Communication;
use App\Models\CommunicationTemplate;
use App\Models\Employee;
use App\Services\Communication\CommunicationSendService;
use App\Services\Communication\CommunicationTemplateRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunicationSendRequestController extends Controller
{
    public function __construct(
        protected CommunicationSendService $sendService,
        protected CommunicationTemplateRenderService $renderService,
    ) {}

    public function create(Request $request): View
    {
        $this->authorize('send', Communication::class);

        $client = $request->filled('client_id')
            ? Client::findOrFail($request->integer('client_id'))
            : null;

        $templates = CommunicationTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('pages.communications.send-request', [
            'title' => 'Send Request',
            'client' => $client,
            'templates' => $templates,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('send', Communication::class);

        $template = CommunicationTemplate::findOrFail($request->integer('template_id'));
        $client = $request->filled('client_id') ? Client::find($request->integer('client_id')) : null;
        $employee = $request->filled('employee_id') ? Employee::find($request->integer('employee_id')) : null;

        $variables = $this->renderService->buildVariables($client, $employee);
        $allowed = $template->allowed_variables ?: config('communications.template_variables');

        return response()->json([
            'subject' => $this->renderService->render($template->subject, $variables, $allowed, true),
            'body' => $this->renderService->render($template->body, $variables, $allowed, true),
        ]);
    }

    public function store(SendCommunicationRequestRequest $request): RedirectResponse
    {
        $template = $request->template();
        $this->authorize('send', Communication::class);

        if ((int) $template->organization_id !== (int) auth()->user()->organization_id) {
            abort(403);
        }

        $client = $request->client();
        $employee = $request->filled('employee_id')
            ? Employee::findOrFail($request->integer('employee_id'))
            : null;

        $communication = $this->sendService->sendFromTemplate(
            $template,
            $request->user(),
            $client,
            $employee,
            $request->only([
                'subject', 'body', 'recipient_email', 'recipient_fax', 'recipient_phone', 'recipient_name',
            ]),
            $request->file('attachment')
        );

        $redirect = $client
            ? redirect()->route('clients.show', $client->id)
            : redirect()->route('communications.show', $communication);

        return $redirect->with(
            'success',
            $communication->status === Communication::STATUS_SENT
                ? 'Communication sent successfully.'
                : 'Communication logged with delivery failure.'
        );
    }
}
