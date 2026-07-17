<?php

namespace App\Services;

use App\Models\AiAgent;
use App\Models\Client;
use App\Models\Document;
use App\Models\Employee;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormsTrackingService
{
    public function pageData(?int $orgId, Request $request, User $user): array
    {
        $this->expireStaleSubmissions($orgId);

        $canManage = $user->hasPermission('manage_forms');
        $filters = $this->resolveFilters($request);

        return [
            'title' => 'Forms',
            'templates' => $this->templates($orgId, includeInactive: $canManage),
            'submissions' => $this->submissions($orgId, $filters),
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'templateFilterOptions' => $this->templateFilterOptions($orgId),
            'targetTypeOptions' => $this->targetTypeOptions(),
            'canManageForms' => $canManage,
            'csrfToken' => csrf_token(),
        ];
    }

    public function fillPage(?int $orgId, int $templateId, Request $request): ?array
    {
        $template = FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->find($templateId);

        if (! $template) {
            return null;
        }

        $subjects = $template->target_type === FormTemplate::TARGET_EMPLOYEE
            ? $this->employeeSubjects($orgId)
            : $this->clientSubjects($orgId);

        $subjectId = $request->integer('subject_id') ?: null;
        $subject = $subjectId ? $this->resolveSubject($template->target_type, $subjectId, $orgId) : null;

        return [
            'title' => $template->name ?: 'Fill Form',
            'template' => $this->serializeTemplate($template),
            'subjects' => $subjects,
            'subject' => $subject ? $this->serializeSubject($subject) : null,
            'prefill' => $subject ? $this->buildPrefill($template, $subject) : [],
            'csrfToken' => csrf_token(),
        ];
    }

    public function storeSubmission(
        ?int $orgId,
        int $templateId,
        int $subjectId,
        array $fieldValues,
        User $user,
        ?string $action = 'save',
    ): FormSubmission {
        $template = FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->findOrFail($templateId);

        $subjectType = $template->target_type === FormTemplate::TARGET_EMPLOYEE
            ? Employee::class
            : Client::class;

        $status = match ($action) {
            'send_signature' => FormSubmission::STATUS_AWAITING_SIGNATURE,
            'sign' => FormSubmission::STATUS_SIGNED,
            default => FormSubmission::STATUS_DRAFT,
        };

        $submission = FormSubmission::create([
            'organization_id' => $orgId ?? $user->organization_id,
            'form_template_id' => $template->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => $status === FormSubmission::STATUS_AWAITING_SIGNATURE
                ? FormSubmission::STATUS_DRAFT
                : $status,
            'field_values' => $fieldValues,
            'fields_snapshot' => in_array($action, ['sign', 'send_signature'], true)
                ? ($template->fields ?? [])
                : null,
            'created_by_user_id' => $user->id,
            'signed_at' => $action === 'sign' ? now() : null,
            'signed_by_name' => $action === 'sign' ? ($fieldValues['signature_name'] ?? $user->name) : null,
            'signature_image' => $action === 'sign' ? ($fieldValues['signature_image'] ?? null) : null,
            'locked_at' => $action === 'sign' ? now() : null,
            'expires_at' => null,
        ]);

        if ($action === 'sign') {
            $this->fileSignedDocument($submission, $template);
        }

        if ($action === 'send_signature') {
            $submission = app(FormEsignService::class)->sendForSignature($submission->fresh(['template', 'subject']));
        }

        return $submission->fresh(['template']);
    }

    public function signSubmission(?int $orgId, int $submissionId, string $signedByName): FormSubmission
    {
        $submission = FormSubmission::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with('template')
            ->findOrFail($submissionId);

        if ($submission->isLocked()) {
            throw new \RuntimeException('This form is locked and cannot be edited.');
        }

        $template = $submission->template;

        $submission->update([
            'status' => FormSubmission::STATUS_SIGNED,
            'signed_at' => now(),
            'signed_by_name' => $signedByName,
            'locked_at' => now(),
            'fields_snapshot' => $submission->fields_snapshot ?: ($template?->fields ?? []),
            'expires_at' => null,
        ]);

        $this->fileSignedDocument($submission->fresh(['template']), $template);

        return $submission->fresh(['template', 'document']);
    }

    public function showPage(?int $orgId, int $submissionId): ?array
    {
        $submission = $this->findSubmission($orgId, $submissionId);

        if (! $submission) {
            return null;
        }

        return $this->serializeSubmissionPage($submission, readOnly: true);
    }

    public function editPage(?int $orgId, int $submissionId): ?array
    {
        $submission = $this->findSubmission($orgId, $submissionId);

        if (! $submission || $submission->isLocked()) {
            return null;
        }

        return $this->serializeSubmissionPage($submission, readOnly: false);
    }

    public function updateSubmission(
        ?int $orgId,
        int $submissionId,
        array $fieldValues,
        User $user,
        string $action = 'save',
    ): FormSubmission {
        $submission = $this->findSubmission($orgId, $submissionId);

        if (! $submission) {
            throw new \RuntimeException('Form submission not found.');
        }

        if ($submission->isLocked()) {
            throw new \RuntimeException('This form is locked and cannot be edited.');
        }

        $status = match ($action) {
            'send_signature' => FormSubmission::STATUS_AWAITING_SIGNATURE,
            'sign' => FormSubmission::STATUS_SIGNED,
            default => FormSubmission::STATUS_DRAFT,
        };

        $payload = [
            'field_values' => $fieldValues,
            'status' => $action === 'send_signature'
                ? $submission->status
                : $status,
            'signed_at' => $action === 'sign' ? now() : null,
            'signed_by_name' => $action === 'sign' ? ($fieldValues['signature_name'] ?? $user->name) : null,
            'signature_image' => $action === 'sign' ? ($fieldValues['signature_image'] ?? null) : null,
            'locked_at' => $action === 'sign' ? now() : null,
            'expires_at' => $action === 'send_signature' ? null : $submission->expires_at,
        ];

        if ($action === 'sign') {
            $payload['fields_snapshot'] = $submission->fields_snapshot
                ?: ($submission->template?->fields ?? []);
            $payload['status'] = FormSubmission::STATUS_SIGNED;
        }

        if ($action === 'send_signature') {
            $payload['fields_snapshot'] = $submission->fields_snapshot
                ?: ($submission->template?->fields ?? []);
        }

        if ($action === 'save') {
            $payload['status'] = FormSubmission::STATUS_DRAFT;
        }

        $submission->update($payload);

        if ($action === 'sign') {
            $this->fileSignedDocument($submission->fresh(), $submission->template);
        }

        if ($action === 'send_signature') {
            $submission = app(FormEsignService::class)->sendForSignature($submission->fresh(['template', 'subject']));
        }

        return $submission->fresh(['template', 'document']);
    }

    public function deleteSubmission(?int $orgId, int $submissionId): void
    {
        $submission = $this->findSubmission($orgId, $submissionId);

        if (! $submission) {
            throw new \RuntimeException('Form submission not found.');
        }

        if ($submission->isLocked() || $submission->status === FormSubmission::STATUS_SIGNED) {
            throw new \RuntimeException('Signed forms cannot be deleted.');
        }

        $submission->delete();
    }

    public function voidSubmission(?int $orgId, int $id, string $reason, User $user): FormSubmission
    {
        $submission = $this->findSubmission($orgId, $id);

        if (! $submission) {
            throw new \RuntimeException('Form submission not found.');
        }

        $voidable = [
            FormSubmission::STATUS_DRAFT,
            FormSubmission::STATUS_AWAITING_SIGNATURE,
            FormSubmission::STATUS_SIGNED,
        ];

        if (! in_array($submission->status, $voidable, true)) {
            throw new \RuntimeException('Only draft, awaiting signature, or signed forms can be voided.');
        }

        $submission->update([
            'status' => FormSubmission::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => $reason,
            'locked_at' => $submission->locked_at ?? now(),
            'expires_at' => null,
        ]);

        return $submission->fresh(['template']);
    }

    /**
     * Mark awaiting-signature submissions past expires_at as expired.
     */
    public function expireStaleSubmissions(?int $orgId = null): int
    {
        return FormSubmission::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('status', FormSubmission::STATUS_AWAITING_SIGNATURE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update([
                'status' => FormSubmission::STATUS_EXPIRED,
                'locked_at' => now(),
            ]);
    }

    public function createTemplate(?int $orgId, array $data, User $user): FormTemplate
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slugBase = Str::slug($name) ?: 'form-template';
        $slug = $this->uniqueTemplateSlug($orgId ?? $user->organization_id, $slugBase);

        return FormTemplate::create([
            'organization_id' => $orgId ?? $user->organization_id,
            'name' => $name,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'target_type' => $data['target_type'] ?? FormTemplate::TARGET_CLIENT,
            'fields' => $this->normalizeFields($data['fields'] ?? null),
            'requires_signature' => (bool) ($data['requires_signature'] ?? true),
            'is_compliance_required' => (bool) ($data['is_compliance_required'] ?? false),
            'is_active' => true,
        ]);
    }

    public function updateTemplate(?int $orgId, int $templateId, array $data): FormTemplate
    {
        $template = FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->findOrFail($templateId);

        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }
        if (array_key_exists('target_type', $data)) {
            $payload['target_type'] = $data['target_type'];
        }
        if (array_key_exists('fields', $data)) {
            $payload['fields'] = $this->normalizeFields($data['fields']);
        }
        if (array_key_exists('requires_signature', $data)) {
            $payload['requires_signature'] = (bool) $data['requires_signature'];
        }
        if (array_key_exists('is_compliance_required', $data)) {
            $payload['is_compliance_required'] = (bool) $data['is_compliance_required'];
        }
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        $template->update($payload);

        return $template->fresh();
    }

    /**
     * Soft-deactivate a template. Templates with signed submissions cannot be hard-deleted.
     */
    public function deactivateTemplate(?int $orgId, int $templateId): FormTemplate
    {
        $template = FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->findOrFail($templateId);

        // Soft-deactivate only — never hard-delete (preserves signed history).
        $template->update(['is_active' => false]);

        return $template->fresh();
    }

    public function templateFormPage(?int $orgId, ?int $templateId = null): ?array
    {
        $template = null;

        if ($templateId) {
            $template = FormTemplate::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->find($templateId);

            if (! $template) {
                return null;
            }
        }

        $defaultFields = [
            ['key' => 'full_name', 'label' => 'Full name', 'type' => 'text', 'readonly' => true],
            ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea'],
        ];

        return [
            'title' => $template ? 'Edit template' : 'New template',
            'template' => $template ? $this->serializeTemplate($template) : null,
            'fieldsJson' => json_encode(
                $template?->fields ?? $defaultFields,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            'csrfToken' => csrf_token(),
        ];
    }

    public function generateDraftByAgent(
        ?int $orgId,
        int $templateId,
        int $subjectId,
        AiAgent $agent,
    ): FormSubmission {
        $template = FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->findOrFail($templateId);

        $subjectType = $template->target_type === FormTemplate::TARGET_EMPLOYEE
            ? Employee::class
            : Client::class;

        $subject = $this->resolveSubject($template->target_type, $subjectId, $orgId);

        return FormSubmission::create([
            'organization_id' => $orgId ?? $agent->organization_id,
            'form_template_id' => $template->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => FormSubmission::STATUS_DRAFT,
            'field_values' => $this->buildPrefill($template, $subject),
            'created_by_agent_id' => $agent->id,
        ]);
    }

    /**
     * Explicit opt-in path: generate a pre-filled draft and immediately send for
     * e-signature. Not used by scheduled/batch compliance drafting — those call
     * generateDraftByAgent() and leave status Draft. Retain for future agent
     * workflows that intentionally auto-send after human/product approval.
     */
    public function generateAndSendByAgent(
        ?int $orgId,
        int $templateId,
        int $subjectId,
        AiAgent $agent,
    ): FormSubmission {
        if (! $agent->canRunAction('generate_draft')) {
            throw new \RuntimeException('Forms agent is inactive or generate_draft is monitor-only.');
        }

        $draft = $this->generateDraftByAgent($orgId, $templateId, $subjectId, $agent);

        return app(FormEsignService::class)->sendForSignature($draft->fresh(['template', 'subject']));
    }

    /**
     * Generate Draft submissions for compliance-required templates missing a
     * current-month signed form. Leaves forms as Draft for human/client
     * signature — does not auto-send for e-sign.
     *
     * Requires an active Forms agent that can run `generate_draft`.
     *
     * @return array{created: int, skipped: int, agent: string|null}
     */
    public function generateMissingComplianceDrafts(?int $orgId): array
    {
        $this->expireStaleSubmissions($orgId);

        $registry = app(AiAgentRegistryService::class);
        $agent = $orgId
            ? $registry->findBySlug($orgId, 'forms')
            : null;

        if ($orgId && (! $agent || ! $agent->canRunAction('generate_draft'))) {
            return ['created' => 0, 'skipped' => 0, 'agent' => null];
        }

        $templates = FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->where('is_active', true)
            ->where('is_compliance_required', true)
            ->where('target_type', FormTemplate::TARGET_CLIENT)
            ->get();

        $created = 0;
        $skipped = 0;
        $periodStart = now()->startOfMonth();

        foreach ($templates as $template) {
            $templateOrgId = $template->organization_id;
            $formsAgent = $agent ?? $registry->findBySlug($templateOrgId, 'forms');

            if (! $formsAgent || ! $formsAgent->canRunAction('generate_draft')) {
                continue;
            }

            $clients = Client::query()
                ->where('organization_id', $templateOrgId)
                ->where(fn ($q) => $q->where('status', 'Active')->orWhereNull('status'))
                ->get(['id']);

            foreach ($clients as $client) {
                $hasSignedThisMonth = FormSubmission::query()
                    ->where('organization_id', $templateOrgId)
                    ->where('form_template_id', $template->id)
                    ->where('subject_type', Client::class)
                    ->where('subject_id', $client->id)
                    ->where('status', FormSubmission::STATUS_SIGNED)
                    ->where('signed_at', '>=', $periodStart)
                    ->exists();

                if ($hasSignedThisMonth) {
                    $skipped++;

                    continue;
                }

                $hasOpenDraft = FormSubmission::query()
                    ->where('organization_id', $templateOrgId)
                    ->where('form_template_id', $template->id)
                    ->where('subject_type', Client::class)
                    ->where('subject_id', $client->id)
                    ->whereIn('status', [
                        FormSubmission::STATUS_DRAFT,
                        FormSubmission::STATUS_AWAITING_SIGNATURE,
                    ])
                    ->exists();

                if ($hasOpenDraft) {
                    $skipped++;

                    continue;
                }

                $this->generateDraftByAgent($templateOrgId, $template->id, $client->id, $formsAgent);
                $created++;
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'agent' => $agent?->name ?? 'Forms / Documentation Agent',
        ];
    }

    private function templates(?int $orgId, bool $includeInactive = false): array
    {
        return FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (FormTemplate $t) => $this->serializeTemplate($t))
            ->all();
    }

    /**
     * @return array{
     *     status: string|null,
     *     template_id: int|null,
     *     target_type: string|null,
     *     search: string|null,
     *     date_from: string|null,
     *     date_to: string|null,
     *     per_page: int
     * }
     */
    private function resolveFilters(Request $request): array
    {
        $status = $request->input('status');
        $allowedStatuses = [
            FormSubmission::STATUS_DRAFT,
            FormSubmission::STATUS_AWAITING_SIGNATURE,
            FormSubmission::STATUS_SIGNED,
            FormSubmission::STATUS_EXPIRED,
            FormSubmission::STATUS_VOIDED,
        ];

        $targetType = $request->input('target_type');
        $allowedTargets = [FormTemplate::TARGET_CLIENT, FormTemplate::TARGET_EMPLOYEE];

        $perPage = (int) $request->input('per_page', 15);
        if (! in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 15;
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        return [
            'status' => is_string($status) && in_array($status, $allowedStatuses, true) ? $status : null,
            'template_id' => $request->integer('template_id') ?: null,
            'target_type' => is_string($targetType) && in_array($targetType, $allowedTargets, true) ? $targetType : null,
            'search' => filled($request->input('search')) ? trim((string) $request->input('search')) : null,
            'date_from' => is_string($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : null,
            'date_to' => is_string($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : null,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array{
     *     status: string|null,
     *     template_id: int|null,
     *     target_type: string|null,
     *     search: string|null,
     *     date_from: string|null,
     *     date_to: string|null,
     *     per_page: int
     * }  $filters
     */
    private function submissions(?int $orgId, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = FormSubmission::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with(['template', 'subject'])
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['template_id'], fn ($q, $templateId) => $q->where('form_template_id', $templateId))
            ->when($filters['target_type'] === FormTemplate::TARGET_CLIENT, fn ($q) => $q->where('subject_type', Client::class))
            ->when($filters['target_type'] === FormTemplate::TARGET_EMPLOYEE, fn ($q) => $q->where('subject_type', Employee::class))
            ->when($filters['date_from'], fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'], fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($filters['search'], function ($q, $search) {
                $like = '%'.$search.'%';
                $q->where(function ($inner) use ($like, $search) {
                    $inner->whereHas('template', fn ($tq) => $tq->where('name', 'like', $like))
                        ->orWhereHasMorph('subject', [Client::class, Employee::class], function ($sq) use ($like, $search) {
                            $sq->where(function ($nameQ) use ($like, $search) {
                                $nameQ->where('first_name', 'like', $like)
                                    ->orWhere('last_name', 'like', $like);

                                $parts = preg_split('/\s+/', trim($search), 2);
                                if (is_array($parts) && count($parts) === 2) {
                                    $nameQ->orWhere(function ($both) use ($parts) {
                                        $both->where('first_name', 'like', '%'.$parts[0].'%')
                                            ->where('last_name', 'like', '%'.$parts[1].'%');
                                    });
                                }
                            });
                        });
                });
            })
            ->orderByDesc('updated_at');

        return $query
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (FormSubmission $s) => [
                'id' => $s->id,
                'form' => $s->template?->name ?? '—',
                'person' => $s->subjectName(),
                'date' => $s->created_at?->format('M j, Y') ?? '—',
                'status' => $s->status,
                'status_label' => $this->statusLabel($s->status),
                'locked' => $s->isLocked(),
                'can_edit' => ! $s->isLocked(),
                'can_delete' => ! $s->isLocked() && $s->status !== FormSubmission::STATUS_SIGNED,
                'view_url' => route('forms.submissions.show', $s->id),
                'edit_url' => route('forms.submissions.edit', $s->id),
                'download_url' => $s->document_id
                    ? route('forms.download', $s->id)
                    : null,
            ]);
    }

    private function templateFilterOptions(?int $orgId): array
    {
        return FormTemplate::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (FormTemplate $t) => [
                'value' => (string) $t->id,
                'label' => $t->name,
            ])
            ->all();
    }

    private function targetTypeOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All people'],
            ['value' => FormTemplate::TARGET_CLIENT, 'label' => 'Clients'],
            ['value' => FormTemplate::TARGET_EMPLOYEE, 'label' => 'Caregivers'],
        ];
    }

    private function serializeTemplate(FormTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description,
            'target_type' => $template->target_type,
            'target_label' => $template->targetLabel(),
            'fields' => $template->fields ?? [],
            'requires_signature' => $template->requires_signature,
            'is_compliance_required' => $template->is_compliance_required,
            'is_active' => $template->is_active,
            'fill_url' => route('forms.fill', $template->id),
            'edit_url' => route('forms.templates.edit', $template->id),
        ];
    }

    private function buildPrefill(FormTemplate $template, Client|Employee $subject): array
    {
        $base = [
            'full_name' => trim($subject->first_name.' '.$subject->last_name),
            'first_name' => $subject->first_name,
            'last_name' => $subject->last_name,
        ];

        if ($subject instanceof Client) {
            $base['dob'] = $subject->dob?->format('m/d/Y') ?? '';
            $base['address'] = $subject->address ?? '';
            $base['program'] = $subject->mco_name ?? '';
            $base['phone'] = $subject->phone ?? '';
        }

        if ($subject instanceof Employee) {
            $base['position'] = $subject->position ?? '';
            $base['email'] = $subject->email ?? '';
        }

        return $base;
    }

    private function serializeSubject(Client|Employee $subject): array
    {
        return [
            'id' => $subject->id,
            'name' => trim($subject->first_name.' '.$subject->last_name),
            'type' => $subject instanceof Client ? 'client' : 'employee',
        ];
    }

    private function resolveSubject(string $targetType, int $subjectId, ?int $orgId): Client|Employee
    {
        if ($targetType === FormTemplate::TARGET_EMPLOYEE) {
            return Employee::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->findOrFail($subjectId);
        }

        return Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->findOrFail($subjectId);
    }

    private function clientSubjects(?int $orgId): array
    {
        return Client::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Client $c) => ['id' => $c->id, 'name' => trim($c->first_name.' '.$c->last_name)])
            ->all();
    }

    private function employeeSubjects(?int $orgId): array
    {
        return Employee::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => trim($e->first_name.' '.$e->last_name)])
            ->all();
    }

    public function fileSignedDocument(FormSubmission $submission, FormTemplate $template): void
    {
        $subject = $submission->subject;
        $filename = str($template->slug)->slug().'-'.$submission->id.'.pdf';
        $path = 'documents/forms/'.$filename;

        // D9: render the real signed PDF so the Documents entry is downloadable.
        $fileSize = $this->renderSignedPdf($submission, $template, $path);

        $document = Document::create([
            'organization_id' => $submission->organization_id,
            'documentable_type' => $submission->subject_type,
            'documentable_id' => $submission->subject_id,
            'name' => $template->name.' (Signed)',
            'path' => $path,
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'file_size' => $fileSize,
            'original_filename' => $filename,
            'type' => 'form',
            'category' => $template->is_compliance_required ? 'Compliance' : 'Forms',
            'verification_status' => $template->is_compliance_required ? 'Verified' : 'Pending',
            'is_signed' => true,
            'signed_at' => now(),
            'uploaded_by' => auth()->id(),
        ]);

        $submission->update(['document_id' => $document->id]);

        $this->markCompliancePeriodSubmitted($submission, $template);
    }

    /**
     * Render the signed submission to a stored PDF (client review D9).
     * Returns the stored file size; filing metadata still proceeds if
     * PDF rendering fails, so signing is never blocked.
     */
    private function renderSignedPdf(FormSubmission $submission, FormTemplate $template, string $path): int
    {
        try {
            $fields = $submission->fields_snapshot ?? $template->fields ?? [];

            $output = \Barryvdh\DomPDF\Facade\Pdf::loadView('pages.forms.pdf', [
                'submission' => $submission,
                'template' => $template,
                'fields' => $fields,
                'subjectName' => $submission->subjectName(),
                'values' => $submission->field_values ?? [],
            ])->setPaper('a4')->output();

            \Illuminate\Support\Facades\Storage::disk('local')->put($path, $output);

            return strlen($output);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Signed form PDF generation failed', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Signing a compliance-required form also flips the subject's current
     * monthly compliance form to Submitted (client review D9) so the
     * Compliance tab stays in sync without a manual update.
     */
    private function markCompliancePeriodSubmitted(FormSubmission $submission, FormTemplate $template): void
    {
        if (! $template->is_compliance_required) {
            return;
        }

        \App\Models\ComplianceForm::query()
            ->when(
                $submission->subject_type === Client::class,
                fn ($q) => $q->where('client_id', $submission->subject_id),
                fn ($q) => $q->where('employee_id', $submission->subject_id),
            )
            ->where('period', now()->format('Y-m'))
            ->whereIn('status', [\App\Models\ComplianceForm::STATUS_DUE, \App\Models\ComplianceForm::STATUS_AWAITING])
            ->get()
            ->each(fn (\App\Models\ComplianceForm $form) => $form->update([
                'status' => \App\Models\ComplianceForm::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]));
    }

    private function statusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All statuses'],
            ['value' => FormSubmission::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => FormSubmission::STATUS_AWAITING_SIGNATURE, 'label' => 'Awaiting signature'],
            ['value' => FormSubmission::STATUS_SIGNED, 'label' => 'Signed / Complete'],
            ['value' => FormSubmission::STATUS_EXPIRED, 'label' => 'Expired'],
            ['value' => FormSubmission::STATUS_VOIDED, 'label' => 'Voided'],
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            FormSubmission::STATUS_DRAFT => 'Draft',
            FormSubmission::STATUS_AWAITING_SIGNATURE => 'Awaiting signature',
            FormSubmission::STATUS_SIGNED => 'Signed / Complete',
            FormSubmission::STATUS_EXPIRED => 'Expired',
            FormSubmission::STATUS_VOIDED => 'Voided',
            default => ucfirst($status),
        };
    }

    private function findSubmission(?int $orgId, int $submissionId): ?FormSubmission
    {
        return FormSubmission::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with(['template', 'subject'])
            ->find($submissionId);
    }

    private function serializeSubmissionPage(FormSubmission $submission, bool $readOnly): array
    {
        $template = $submission->template;
        $subject = $submission->subject;
        $fields = $submission->fields_snapshot ?? $template?->fields ?? [];

        // Legacy signed/awaiting rows may predate snapshots — freeze live fields once.
        if ($submission->fields_snapshot === null
            && $template
            && in_array($submission->status, [
                FormSubmission::STATUS_SIGNED,
                FormSubmission::STATUS_AWAITING_SIGNATURE,
                FormSubmission::STATUS_VOIDED,
                FormSubmission::STATUS_EXPIRED,
            ], true)
        ) {
            $fields = $template->fields ?? [];
            $submission->forceFill(['fields_snapshot' => $fields])->saveQuietly();
        }

        $values = $submission->field_values ?? [];

        $canVoid = in_array($submission->status, [
            FormSubmission::STATUS_DRAFT,
            FormSubmission::STATUS_AWAITING_SIGNATURE,
            FormSubmission::STATUS_SIGNED,
        ], true);

        return [
            'title' => $template?->name ?: 'Form Submission',
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'status_label' => $this->statusLabel($submission->status),
                'locked' => $submission->isLocked(),
                'signed_at' => $submission->signed_at?->format('M j, Y g:i A'),
                'signed_by_name' => $submission->signed_by_name,
                'signature_image' => $submission->signature_image
                    ?: data_get($submission->field_values, 'signature_image'),
                'created_at' => $submission->created_at?->format('M j, Y g:i A'),
                'voided_at' => $submission->voided_at?->format('M j, Y g:i A'),
                'void_reason' => $submission->void_reason,
                'can_void' => $canVoid,
                'download_url' => $submission->document_id
                    ? route('forms.download', $submission->id)
                    : null,
            ],
            'template' => $template ? $this->serializeTemplate($template) : null,
            'subject' => $subject instanceof Client || $subject instanceof Employee
                ? $this->serializeSubject($subject)
                : ['id' => $submission->subject_id, 'name' => $submission->subjectName(), 'type' => 'unknown'],
            'fields' => collect($fields)->map(function (array $field) use ($values) {
                return [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'readonly' => ! empty($field['readonly']),
                    'value' => $values[$field['key']] ?? '',
                ];
            })->all(),
            'readOnly' => $readOnly,
            'prefill' => $values,
            'csrfToken' => csrf_token(),
        ];
    }

    /**
     * @param  mixed  $fields
     * @return list<array{key: string, label: string, type?: string, readonly?: bool}>
     */
    private function normalizeFields(mixed $fields): array
    {
        if (is_string($fields)) {
            $decoded = json_decode($fields, true);
            $fields = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($fields)) {
            return [];
        }

        return collect($fields)
            ->filter(fn ($f) => is_array($f) && ! empty($f['key']) && ! empty($f['label']))
            ->map(fn (array $f) => [
                'key' => (string) $f['key'],
                'label' => (string) $f['label'],
                'type' => $f['type'] ?? 'text',
                'readonly' => ! empty($f['readonly']),
            ])
            ->values()
            ->all();
    }

    private function uniqueTemplateSlug(?int $orgId, string $base): string
    {
        $slug = $base;
        $i = 2;

        while (
            FormTemplate::query()
                ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
