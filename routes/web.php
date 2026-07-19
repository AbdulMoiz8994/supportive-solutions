<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(\App\Helpers\MenuHelper::getLandingRoute());
    }
    return redirect()->route('signin');
});

Route::get('/esign/{token}', [\App\Http\Controllers\FormEsignController::class, 'show'])->name('forms.esign.show');
Route::post('/esign/{token}', [\App\Http\Controllers\FormEsignController::class, 'sign'])->name('forms.esign.sign');

    Route::middleware(['auth', '2fa'])->group(function () {
        
        // 1. Super Admin Only Routes
        Route::middleware(['role:Super Administrator'])->group(function () {
            Route::get('/organizations', function () {
                return view('pages.blank', ['title' => 'Organization Management']);
            })->name('organizations');

            // User Management (CRUD)
            Route::get('/users', [\App\Http\Controllers\UserManagementController::class, 'index'])->name('users.index');
            Route::post('/users', [\App\Http\Controllers\UserManagementController::class, 'store'])->name('users.store');
            Route::put('/users/{id}', [\App\Http\Controllers\UserManagementController::class, 'update'])->name('users.update');
            Route::delete('/users/{id}', [\App\Http\Controllers\UserManagementController::class, 'destroy'])->name('users.destroy');

            // Location Management (CRUD)
            Route::get('/locations', [\App\Http\Controllers\LocationManagementController::class, 'index'])->name('locations.index');
            Route::post('/locations', [\App\Http\Controllers\LocationManagementController::class, 'store'])->name('locations.store');
            Route::put('/locations/{id}', [\App\Http\Controllers\LocationManagementController::class, 'update'])->name('locations.update');
            Route::delete('/locations/{id}', [\App\Http\Controllers\LocationManagementController::class, 'destroy'])->name('locations.destroy');

            // API Key Management (Super Admin only)
            Route::get('/api-keys', [\App\Http\Controllers\ApiKeyController::class, 'index'])->name('api-keys');
            Route::post('/api-keys', [\App\Http\Controllers\ApiKeyController::class, 'store'])->name('api-keys.store');
            Route::put('/api-keys/{id}', [\App\Http\Controllers\ApiKeyController::class, 'update'])->name('api-keys.update');
            Route::delete('/api-keys/{id}', [\App\Http\Controllers\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
            Route::put('/api-keys/{id}/toggle', [\App\Http\Controllers\ApiKeyController::class, 'toggleActive'])->name('api-keys.toggle');
            Route::post('/api-keys/{id}/regenerate', [\App\Http\Controllers\ApiKeyController::class, 'regenerate'])->name('api-keys.regenerate');

            // Global Settings (Super Admin only)
            Route::get('/settings/global', [\App\Http\Controllers\GlobalSettingsController::class, 'index'])->name('settings.global');
            Route::post('/settings/global', [\App\Http\Controllers\GlobalSettingsController::class, 'update'])->name('settings.global.update');
            Route::post('/settings/global/agency', [\App\Http\Controllers\GlobalSettingsController::class, 'updateAgencyIdentity'])->name('settings.global.agency');
            Route::post('/settings/global/credential-vault', [\App\Http\Controllers\GlobalSettingsController::class, 'updateCredentialVault'])->name('settings.global.credential-vault');
            Route::get('/settings/global/audit-log', [\App\Http\Controllers\GlobalSettingsController::class, 'auditLog'])->name('settings.global.audit-log');
            Route::post('/settings/global/activation-codes', [\App\Http\Controllers\GlobalSettingsController::class, 'generateActivationCode'])->name('settings.global.activation-codes.store');
            Route::post('/settings/global/activation-codes/{activationCode}/resend', [\App\Http\Controllers\GlobalSettingsController::class, 'resendActivationCode'])->name('settings.global.activation-codes.resend');
            Route::post('/settings/global/activation-codes/{activationCode}/revoke', [\App\Http\Controllers\GlobalSettingsController::class, 'revokeActivationCode'])->name('settings.global.activation-codes.revoke');
            Route::post('/settings/global/integrations/test', [\App\Http\Controllers\GlobalSettingsController::class, 'testIntegration'])->name('settings.global.integrations.test');
        });

        // Settings home (platform + agency administrators)
        Route::middleware(['role:Super Administrator,Administrator'])->group(function () {
            Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
            Route::get('/settings/roles', [\App\Http\Controllers\SettingsController::class, 'roles'])->name('settings.roles');
            Route::get('/settings/api-keys', [\App\Http\Controllers\SettingsController::class, 'apiKeys'])->name('settings.api-keys');
        });

        // 2. Admin & Staff (Office Team) Routes
        Route::middleware(['role:Super Administrator,Administrator,Operations Staff'])->group(function () {
            Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard')->middleware('permission:view_dashboard');
            Route::post('/dashboard/approve/{type}/{id}', [\App\Http\Controllers\DashboardController::class, 'approve'])->name('dashboard.approve')->whereNumber('id')->middleware('permission:approve_queue_items');
            Route::get('/sidebar/badges', \App\Http\Controllers\SidebarBadgesController::class)->name('sidebar.badges');

            // Sidebar / dashboard nav modules awaiting their own designs — safe placeholders for now.
            Route::get('/workflow-queues', [\App\Http\Controllers\WorkflowQueuesController::class, 'index'])->name('workflow-queues');
            Route::get('/workflow-queues/approvals', [\App\Http\Controllers\WorkflowQueuesController::class, 'loadApprovals'])->name('workflow-queues.approvals');
            Route::post('/workflow-queues/{slug}/action', [\App\Http\Controllers\WorkflowQueuesController::class, 'action'])->name('workflow-queues.action')->middleware('permission:approve_queue_items');
            Route::get('/authorizations', [\App\Http\Controllers\AuthorizationController::class, 'index'])->name('authorizations');
            Route::get('/background-checks', [\App\Http\Controllers\BackgroundCheckController::class, 'index'])->name('background-checks');
            Route::get('/reports', [\App\Http\Controllers\Reports\ReportsController::class, 'index'])->name('reports.index');
            Route::get('/reports/visit', fn () => redirect()->route('visit-reports'))->name('reports.visit');
            Route::get('/reports/schedule', [\App\Http\Controllers\Reports\ReportsController::class, 'scheduleForm'])->name('reports.schedule');
            Route::post('/reports/schedule', [\App\Http\Controllers\Reports\ReportsController::class, 'scheduleStore'])->name('reports.schedule.store');
            Route::delete('/reports/schedule/{schedule}', [\App\Http\Controllers\Reports\ReportsController::class, 'scheduleDestroy'])->name('reports.schedule.destroy');
            Route::post('/reports/custom/run', [\App\Http\Controllers\Reports\ReportsController::class, 'customRun'])->name('reports.custom.run');
            Route::post('/reports/custom/save', [\App\Http\Controllers\Reports\ReportsController::class, 'customSave'])->name('reports.custom.save');
            Route::get('/reports/{report}/export', [\App\Http\Controllers\Reports\ReportsController::class, 'export'])->name('reports.export');
            Route::get('/reports/{report}', [\App\Http\Controllers\Reports\ReportsController::class, 'show'])->name('reports.show');
            Route::get('/forms', [\App\Http\Controllers\FormsTrackingController::class, 'index'])->name('forms')->middleware('permission:view_forms');
            Route::get('/forms/templates/create', [\App\Http\Controllers\FormsTrackingController::class, 'createTemplate'])->name('forms.templates.create')->middleware('permission:manage_forms');
            Route::post('/forms/templates', [\App\Http\Controllers\FormsTrackingController::class, 'storeTemplate'])->name('forms.templates.store')->middleware('permission:manage_forms');
            Route::get('/forms/templates/{template}/edit', [\App\Http\Controllers\FormsTrackingController::class, 'editTemplate'])->name('forms.templates.edit')->middleware('permission:manage_forms');
            Route::put('/forms/templates/{template}', [\App\Http\Controllers\FormsTrackingController::class, 'updateTemplate'])->name('forms.templates.update')->middleware('permission:manage_forms');
            Route::post('/forms/templates/{template}/deactivate', [\App\Http\Controllers\FormsTrackingController::class, 'deactivateTemplate'])->name('forms.templates.deactivate')->middleware('permission:manage_forms');
            Route::get('/forms/templates/{template}/fill', [\App\Http\Controllers\FormsTrackingController::class, 'fill'])->name('forms.fill')->middleware('permission:manage_forms');
            Route::post('/forms/templates/{template}', [\App\Http\Controllers\FormsTrackingController::class, 'store'])->name('forms.store')->middleware('permission:manage_forms');
            Route::post('/forms/generate-drafts', [\App\Http\Controllers\FormsTrackingController::class, 'generateDrafts'])->name('forms.generate-drafts')->middleware('permission:manage_forms');
            Route::get('/forms/submissions/{submission}', [\App\Http\Controllers\FormsTrackingController::class, 'show'])->name('forms.submissions.show')->middleware('permission:view_forms');
            Route::get('/forms/submissions/{submission}/edit', [\App\Http\Controllers\FormsTrackingController::class, 'edit'])->name('forms.submissions.edit')->middleware('permission:manage_forms');
            Route::put('/forms/submissions/{submission}', [\App\Http\Controllers\FormsTrackingController::class, 'update'])->name('forms.submissions.update')->middleware('permission:manage_forms');
            Route::delete('/forms/submissions/{submission}', [\App\Http\Controllers\FormsTrackingController::class, 'destroy'])->name('forms.submissions.destroy')->middleware('permission:manage_forms');
            Route::post('/forms/submissions/{submission}/sign', [\App\Http\Controllers\FormsTrackingController::class, 'sign'])->name('forms.sign')->middleware('permission:manage_forms');
            Route::post('/forms/submissions/{submission}/void', [\App\Http\Controllers\FormsTrackingController::class, 'void'])->name('forms.submissions.void')->middleware('permission:manage_forms');
            Route::get('/forms/submissions/{submission}/download', [\App\Http\Controllers\FormsTrackingController::class, 'download'])->name('forms.download')->middleware('permission:view_forms');
            Route::get('/exploration', [\App\Http\Controllers\DataExplorationController::class, 'index'])->name('exploration')->middleware('permission:view_data_exploration');
            Route::get('/data-exploration', [\App\Http\Controllers\DataExplorationController::class, 'index'])->name('data-exploration')->middleware('permission:view_data_exploration');
            Route::post('/data-exploration/query', [\App\Http\Controllers\DataExplorationController::class, 'query'])->name('data-exploration.query')->middleware('permission:view_data_exploration');
            Route::post('/data-exploration/save-view', [\App\Http\Controllers\DataExplorationController::class, 'saveView'])->name('data-exploration.save-view')->middleware('permission:view_data_exploration');
            Route::delete('/data-exploration/views/{id}', [\App\Http\Controllers\DataExplorationController::class, 'deleteView'])->name('data-exploration.delete-view')->middleware('permission:view_data_exploration');
            Route::get('/data-exploration/export', [\App\Http\Controllers\DataExplorationController::class, 'export'])->name('data-exploration.export')->middleware('permission:view_data_exploration');
            Route::get('/efax', [\App\Http\Controllers\EfaxController::class, 'create'])->name('efax.compose')->middleware('permission:view_communications');
            Route::post('/efax', [\App\Http\Controllers\EfaxController::class, 'store'])->name('efax.send')->middleware('permission:send_communications');

            Route::get('/clients', [\App\Http\Controllers\ClientController::class, 'index'])->name('clients.index')->middleware('permission:view_clients');
            Route::get('/clients/export', [\App\Http\Controllers\ClientController::class, 'export'])->name('clients.export')->middleware('permission:view_clients');
            Route::post('/clients', [\App\Http\Controllers\ClientController::class, 'store'])->name('clients.store')->middleware('permission:add_clients');
            
            // BRD Parity Helper Routes (Moved above {id} routes to prevent conflict)
            Route::get('/visit-reports', [\App\Http\Controllers\VisitReportsController::class, 'index'])->name('visit-reports')->middleware('permission:view_visit_reports');
            Route::get('/visit-reports/{id}', [\App\Http\Controllers\VisitReportsController::class, 'show'])->name('visit-reports.show')->whereNumber('id')->middleware('permission:view_visit_reports');
            Route::post('/visit-reports/{id}/propose-correction', [\App\Http\Controllers\VisitReportsController::class, 'proposeCorrection'])->name('visit-reports.propose-correction')->whereNumber('id')->middleware('permission:manage_visit_reports');
            Route::post('/visit-reports/{id}/approve-correction', [\App\Http\Controllers\VisitReportsController::class, 'approveCorrection'])->name('visit-reports.approve-correction')->whereNumber('id')->middleware('permission:manage_visit_reports');
            Route::post('/visit-reports/{id}/approve-location', [\App\Http\Controllers\VisitReportsController::class, 'approveLocation'])->name('visit-reports.approve-location')->whereNumber('id')->middleware('permission:manage_visit_reports');
            Route::post('/visit-reports/{id}/mark-missed', [\App\Http\Controllers\VisitReportsController::class, 'markMissed'])->name('visit-reports.mark-missed')->whereNumber('id')->middleware('permission:manage_visit_reports');
            Route::get('/dashboard/forms', fn () => redirect()->route('forms'))->name('dashboard.forms');
            Route::get('/tasks', [\App\Http\Controllers\TasksController::class, 'index'])->name('tasks')->middleware('permission:view_tasks');
            Route::get('/tasks/{id}', [\App\Http\Controllers\TasksController::class, 'show'])->name('tasks.show')->whereNumber('id')->middleware('permission:view_tasks');
            Route::get('/tasks/{id}/comments', [\App\Http\Controllers\TasksController::class, 'comments'])->name('tasks.comments.index')->whereNumber('id')->middleware('permission:view_tasks');
            Route::post('/tasks/{id}/comments', [\App\Http\Controllers\TasksController::class, 'storeComment'])->name('tasks.comments.store')->whereNumber('id')->middleware('permission:manage_tasks');
            Route::post('/tasks/{id}/submit-for-approval', [\App\Http\Controllers\TasksController::class, 'submitForApproval'])->name('tasks.submit-for-approval')->whereNumber('id')->middleware('permission:manage_tasks');
            Route::post('/tasks', [\App\Http\Controllers\TasksController::class, 'store'])->name('tasks.store')->middleware('permission:manage_tasks');
            Route::put('/tasks/{id}', [\App\Http\Controllers\TasksController::class, 'update'])->name('tasks.update')->whereNumber('id')->middleware('permission:manage_tasks');
            Route::post('/tasks/{id}/status', [\App\Http\Controllers\TasksController::class, 'updateStatus'])->name('tasks.update-status')->whereNumber('id')->middleware('permission:manage_tasks');
            Route::post('/tasks/board-statuses', [\App\Http\Controllers\TasksController::class, 'storeBoardStatus'])->name('tasks.board-statuses.store')->middleware('permission:manage_tasks');
            Route::post('/tasks/board-statuses/reorder', [\App\Http\Controllers\TasksController::class, 'reorderBoardStatuses'])->name('tasks.board-statuses.reorder')->middleware('permission:manage_tasks');
            Route::put('/tasks/board-statuses/{statusId}', [\App\Http\Controllers\TasksController::class, 'updateBoardStatus'])->name('tasks.board-statuses.update')->whereNumber('statusId')->middleware('permission:manage_tasks');
            Route::delete('/tasks/board-statuses/{statusId}', [\App\Http\Controllers\TasksController::class, 'destroyBoardStatus'])->name('tasks.board-statuses.destroy')->whereNumber('statusId')->middleware('permission:manage_tasks');
            Route::get('/work-shifts', fn () => redirect()->route('schedule.board'))->name('work-shifts')->middleware('permission:manage_schedules');
            Route::get('/caregivers', [\App\Http\Controllers\CaregiverController::class, 'index'])->name('caregivers');
            Route::get('/caregivers/export', [\App\Http\Controllers\CaregiverController::class, 'export'])->name('caregivers.export');
            Route::get('/caregivers/create', [\App\Http\Controllers\CaregiverController::class, 'create'])->name('caregivers.create');
            Route::post('/caregivers', [\App\Http\Controllers\CaregiverController::class, 'store'])->name('caregivers.store');
            Route::get('/caregivers/{id}/audit/export', [\App\Http\Controllers\CaregiverController::class, 'exportAudit'])->name('caregivers.audit.export');
            Route::get('/caregivers/{id}/audit/export-pdf', [\App\Http\Controllers\CaregiverController::class, 'exportAuditPdf'])->name('caregivers.audit.export-pdf');
            Route::get('/caregivers/{id}', [\App\Http\Controllers\CaregiverController::class, 'show'])->name('caregivers.show');
            Route::post('/caregivers/{id}', [\App\Http\Controllers\CaregiverController::class, 'update'])->name('caregivers.update');
            Route::post('/caregivers/{id}/assignments', [\App\Http\Controllers\CaregiverController::class, 'storeAssignment'])->name('caregivers.assignments.store');
            Route::post('/caregivers/{id}/notes', [\App\Http\Controllers\CaregiverController::class, 'storeNote'])->name('caregivers.notes.store');
            Route::post('/caregivers/{id}/payroll-portal-setup', [\App\Http\Controllers\CaregiverController::class, 'markPayrollPortalSetup'])->name('caregivers.payroll-portal-setup');
            // Legacy leads list — primary intake workflow is /intakes (see SERVER_SETUP_GUIDE.md)
            Route::get('/leads', [\App\Http\Controllers\LeadController::class, 'index'])->name('leads.index');
            Route::post('/leads', [\App\Http\Controllers\LeadController::class, 'store'])->name('leads.store');
            Route::get('/leads/{id}', [\App\Http\Controllers\LeadController::class, 'show'])->name('leads.show');
            Route::post('/leads/{id}', [\App\Http\Controllers\LeadController::class, 'update'])->name('leads.update');
            Route::get('/contacts', fn () => redirect()->route('directory'))->name('contacts');
            Route::get('/marketing', fn() => view('pages.placeholders.coming-soon', ['title' => 'Marketing & Outreach']))->name('marketing');
            Route::get('/events', fn() => view('pages.placeholders.coming-soon', ['title' => 'Event Calendar']))->name('events');
            
            // Clients Sub-nav placeholders & Main Routes
            Route::get('/clients/create', [\App\Http\Controllers\ClientController::class, 'create'])->name('clients.create')->middleware('permission:add_clients');
            Route::get('/clients/status', fn() => view('pages.placeholders.coming-soon', ['title' => 'Status']))->name('clients.status');
            Route::get('/clients/documents', fn() => view('pages.placeholders.coming-soon', ['title' => 'Client Documents Registry']))->name('clients.documents');
            Route::get('/clients/activity', fn() => view('pages.placeholders.coming-soon', ['title' => 'Client Activity Logs']))->name('clients.activity');
            Route::get('/clients/appointments', fn () => redirect()->route('schedule.board'))->name('clients.appointments');
            Route::get('/clients/time-tasks', fn() => view('pages.placeholders.coming-soon', ['title' => 'Care Details']))->name('clients.time-tasks');
            Route::get('/clients/invoices', fn() => view('pages.placeholders.coming-soon', ['title' => 'Billing']))->name('clients.invoices');
            Route::get('/clients/contacts', fn() => view('pages.placeholders.coming-soon', ['title' => 'Contacts']))->name('clients.contacts');

            Route::get('/clients/{id}/pa-letter/download', [\App\Http\Controllers\ClientController::class, 'downloadPaLetter'])->name('clients.pa-letter.download');
            Route::get('/clients/{id}/authorizations/export', [\App\Http\Controllers\ClientController::class, 'exportAuthorizations'])->name('clients.authorizations.export');
            Route::get('/clients/{id}/documents/download-all', [\App\Http\Controllers\ClientController::class, 'downloadAllDocuments'])->name('clients.documents.download-all');
            Route::get('/clients/{id}', [\App\Http\Controllers\ClientController::class, 'show'])->name('clients.show');
            Route::put('/clients/{id}', [\App\Http\Controllers\ClientController::class, 'update'])->name('clients.update');
            Route::delete('/clients/{id}', [\App\Http\Controllers\ClientController::class, 'destroy'])->name('clients.destroy');
            Route::post('/clients/{id}/change-status', [\App\Http\Controllers\ClientController::class, 'changeStatus'])->name('clients.change-status');
            Route::post('/clients/{id}/care-details', [\App\Http\Controllers\ClientController::class, 'storeCareDetail'])->name('clients.care-details.store');
            Route::put('/clients/{id}/care-details/{careDetail}', [\App\Http\Controllers\ClientController::class, 'updateCareDetail'])->name('clients.care-details.update')->whereNumber(['id', 'careDetail']);
            Route::post('/clients/{id}/assign-caregiver', [\App\Http\Controllers\ClientController::class, 'assignCaregiver'])->name('clients.assign-caregiver');
            Route::put('/clients/{id}/assignment/{assignment}', [\App\Http\Controllers\ClientController::class, 'updateAssignment'])->name('clients.assignment.update')->whereNumber(['id', 'assignment']);
            Route::post('/clients/{id}/wellness-call', [\App\Http\Controllers\ClientController::class, 'triggerWellnessCall'])->name('clients.wellness-call')->whereNumber('id')->middleware('permission:edit_clients');

            // AI (Claude/LLM) automations — scan ID, recognize document, case summary
            Route::post('/ai/scan-id', [\App\Http\Controllers\AiController::class, 'scanId'])->name('ai.scan-id')->middleware('permission:view_clients');
            Route::post('/ai/recognize-document', [\App\Http\Controllers\AiController::class, 'recognizeDocument'])->name('ai.recognize-document')->middleware('permission:view_clients');
            Route::post('/clients/{id}/ai-summary', [\App\Http\Controllers\AiController::class, 'clientSummary'])->name('ai.client-summary')->whereNumber('id')->middleware('permission:view_clients');
            Route::post('/caregivers/{id}/ai-summary', [\App\Http\Controllers\AiController::class, 'caregiverSummary'])->name('ai.caregiver-summary')->whereNumber('id')->middleware('permission:view_staff');
            Route::post('/clients/{id}/requests', [\App\Http\Controllers\ClientController::class, 'storeRequest'])->name('requests.store')->middleware('permission:send_client_requests');
            Route::get('/clients/{id}/ssn', [\App\Http\Controllers\ClientController::class, 'revealSsn'])->name('clients.ssn.reveal')->middleware('permission:view_client_ssn');
            Route::post('/clients/{id}/onboarding-steps', [\App\Http\Controllers\ClientController::class, 'updateOnboardingStep'])->name('clients.onboarding-steps.update');
            Route::post('/clients/{id}/communication-requests', [\App\Http\Controllers\Communication\CommunicationSendRequestController::class, 'store'])->name('communications.client-send')->middleware('permission:send_communications');

            Route::prefix('communications')->name('communications.')->middleware('permission:view_communications')->group(function () {
                Route::get('/', [\App\Http\Controllers\Communication\CommunicationController::class, 'index'])->name('index');
                Route::get('/export', [\App\Http\Controllers\Communication\CommunicationComposeController::class, 'export'])->name('export');
                Route::get('/directory-search', [\App\Http\Controllers\Communication\CommunicationComposeController::class, 'directorySearch'])->name('directory-search')->middleware('permission:send_communications');
                Route::get('/clients/{client}/documents', [\App\Http\Controllers\Communication\CommunicationComposeController::class, 'clientDocuments'])->name('client-documents')->middleware('permission:send_communications');
                Route::post('/compose/message', [\App\Http\Controllers\Communication\CommunicationComposeController::class, 'storeMessage'])->name('compose.message.store')->middleware('permission:send_communications');
                Route::post('/compose/efax', [\App\Http\Controllers\Communication\CommunicationComposeController::class, 'storeEfax'])->name('compose.efax.store')->middleware('permission:send_communications');
                Route::get('/send-request', [\App\Http\Controllers\Communication\CommunicationSendRequestController::class, 'create'])->name('send-request.create')->middleware('permission:send_communications');
                Route::post('/send-request', [\App\Http\Controllers\Communication\CommunicationSendRequestController::class, 'store'])->name('send-request.store')->middleware('permission:send_communications');
                Route::post('/send-request/preview', [\App\Http\Controllers\Communication\CommunicationSendRequestController::class, 'preview'])->name('send-request.preview')->middleware('permission:send_communications');
                Route::post('/manual', [\App\Http\Controllers\Communication\CommunicationController::class, 'storeManual'])->name('manual.store')->middleware('permission:send_communications');
                Route::post('/{communication}/mark-handled', [\App\Http\Controllers\Communication\CommunicationController::class, 'markHandled'])->name('mark-handled')->whereNumber('communication')->middleware('permission:send_communications');
                Route::get('/templates', [\App\Http\Controllers\Communication\CommunicationTemplateController::class, 'index'])->name('templates.index')->middleware('permission:manage_communication_templates');
                Route::post('/templates', [\App\Http\Controllers\Communication\CommunicationTemplateController::class, 'store'])->name('templates.store')->middleware('permission:manage_communication_templates');
                Route::put('/templates/{template}', [\App\Http\Controllers\Communication\CommunicationTemplateController::class, 'update'])->name('templates.update')->middleware('permission:manage_communication_templates');
                Route::delete('/templates/{template}', [\App\Http\Controllers\Communication\CommunicationTemplateController::class, 'destroy'])->name('templates.destroy')->middleware('permission:manage_communication_templates');
                Route::post('/templates/{template}/toggle', [\App\Http\Controllers\Communication\CommunicationTemplateController::class, 'toggle'])->name('templates.toggle')->middleware('permission:manage_communication_templates');
                Route::get('/secure-messages', [\App\Http\Controllers\Communication\SecureMessageThreadController::class, 'index'])->name('secure-messages.index');
                Route::post('/secure-messages', [\App\Http\Controllers\Communication\SecureMessageThreadController::class, 'store'])->name('secure-messages.store');
                Route::get('/secure-messages/{thread}', [\App\Http\Controllers\Communication\SecureMessageThreadController::class, 'show'])->name('secure-messages.show');
                Route::post('/secure-messages/{thread}/reply', [\App\Http\Controllers\Communication\SecureMessageThreadController::class, 'reply'])->name('secure-messages.reply');
                Route::get('/notifications', [\App\Http\Controllers\Communication\CommunicationNotificationController::class, 'index'])->name('notifications.index')->middleware('permission:view_notifications');
                Route::post('/notifications/read-all', [\App\Http\Controllers\Communication\CommunicationNotificationController::class, 'markAllRead'])->name('notifications.read-all')->middleware('permission:view_notifications');
                Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Communication\CommunicationNotificationController::class, 'markRead'])->name('notifications.read')->middleware('permission:view_notifications');
                Route::get('/notifications/unread-count', [\App\Http\Controllers\Communication\CommunicationNotificationController::class, 'unreadCount'])->name('notifications.unread-count')->middleware('permission:view_notifications');
                Route::get('/notifications/recent', [\App\Http\Controllers\Communication\CommunicationNotificationController::class, 'recent'])->name('notifications.recent')->middleware('permission:view_notifications');
                Route::get('/attachments/{attachment}/download', [\App\Http\Controllers\Communication\CommunicationNotificationController::class, 'downloadAttachment'])->name('attachments.download');
                Route::get('/{communication}', [\App\Http\Controllers\Communication\CommunicationController::class, 'show'])->name('show');
            });

            Route::get('/request-templates', [\App\Http\Controllers\RequestTemplateController::class, 'index'])->name('request-templates.index')->middleware('permission:manage_request_templates');
            Route::post('/request-templates', [\App\Http\Controllers\RequestTemplateController::class, 'store'])->name('request-templates.store')->middleware('permission:manage_request_templates');
            Route::put('/request-templates/{id}', [\App\Http\Controllers\RequestTemplateController::class, 'update'])->name('request-templates.update')->middleware('permission:manage_request_templates');
            Route::post('/request-templates/{id}/toggle', [\App\Http\Controllers\RequestTemplateController::class, 'toggle'])->name('request-templates.toggle')->middleware('permission:manage_request_templates');

            Route::get('/employees', [\App\Http\Controllers\EmployeeController::class, 'index'])->name('employees.index');
            Route::post('/employees', [\App\Http\Controllers\EmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{id}', [\App\Http\Controllers\EmployeeController::class, 'show'])->name('employees.show');
            Route::put('/employees/{id}', [\App\Http\Controllers\EmployeeController::class, 'update'])->name('employees.update');
            Route::delete('/employees/{id}', [\App\Http\Controllers\EmployeeController::class, 'destroy'])->name('employees.destroy');

            Route::get('/intakes', [\App\Http\Controllers\IntakeController::class, 'index'])->name('intakes.index');
            Route::post('/intakes', [\App\Http\Controllers\IntakeController::class, 'store'])->name('intakes.store');
            Route::get('/intakes/wizard', [\App\Http\Controllers\IntakeController::class, 'wizard'])->name('intakes.wizard');
            Route::post('/intakes/check-eligibility', [\App\Http\Controllers\IntakeController::class, 'checkEligibility'])->name('intakes.check-eligibility');
            Route::get('/intakes/{id}', [\App\Http\Controllers\IntakeController::class, 'show'])->whereNumber('id')->name('intakes.show');
            Route::get('/intakes/{id}/print', [\App\Http\Controllers\IntakeController::class, 'print'])->name('intakes.print');
            Route::get('/intakes/{id}/download', [\App\Http\Controllers\IntakeController::class, 'download'])->name('intakes.download');
            Route::post('/intakes/{id}/convert', [\App\Http\Controllers\IntakeController::class, 'convert'])->name('intakes.convert');
            Route::put('/intakes/{id}', [\App\Http\Controllers\IntakeController::class, 'update'])->name('intakes.update');
            Route::delete('/intakes/{id}', [\App\Http\Controllers\IntakeController::class, 'destroy'])->name('intakes.destroy');
            Route::post('/intakes/{id}/log-call', [\App\Http\Controllers\IntakeController::class, 'logCall'])->name('intakes.log-call');
            Route::post('/intakes/{id}/schedule-assessment', [\App\Http\Controllers\IntakeController::class, 'scheduleAssessment'])->name('intakes.schedule-assessment');
            Route::post('/intakes/{id}/mark-ineligible', [\App\Http\Controllers\IntakeController::class, 'markIneligible'])->name('intakes.mark-ineligible');
            Route::post('/intakes/{id}/upload-document', [\App\Http\Controllers\IntakeController::class, 'uploadDocument'])->name('intakes.upload-document');

            // Additional Schedule Admin Routes
            Route::get('/schedule/create', [\App\Http\Controllers\ScheduleController::class, 'create'])->name('schedule.create')->middleware('permission:manage_schedules');
            Route::get('/schedule/{id}/edit', [\App\Http\Controllers\ScheduleController::class, 'edit'])->name('schedule.edit')->whereNumber('id')->middleware('permission:manage_schedules');
            Route::put('/schedule/{id}', [\App\Http\Controllers\ScheduleController::class, 'update'])->name('schedule.update')->whereNumber('id');
            Route::patch('/schedule/board/move/{id}', [\App\Http\Controllers\ScheduleController::class, 'moveVisit'])->name('schedule.board.move')->whereNumber('id')->middleware('permission:manage_schedules');
            Route::delete('/schedule/{id}', [\App\Http\Controllers\ScheduleController::class, 'destroy'])->name('schedule.destroy')->whereNumber('id');
            Route::post('/schedule/{id}/cancel', [\App\Http\Controllers\ScheduleController::class, 'cancel'])->name('schedule.cancel')->whereNumber('id');

            Route::get('/directory', [\App\Http\Controllers\DirectoryController::class, 'index'])->name('directory');
            Route::get('/directory/create', [\App\Http\Controllers\DirectoryController::class, 'create'])->name('directory.create');
            Route::post('/directory', [\App\Http\Controllers\DirectoryController::class, 'store'])->name('directory.store');
            Route::get('/directory/{id}', [\App\Http\Controllers\DirectoryController::class, 'show'])->name('directory.show')->whereNumber('id');
            Route::get('/directory/{id}/edit', [\App\Http\Controllers\DirectoryController::class, 'edit'])->name('directory.edit')->whereNumber('id');
            Route::put('/directory/{id}', [\App\Http\Controllers\DirectoryController::class, 'update'])->name('directory.update')->whereNumber('id');
            Route::post('/directory/{id}/test-connection', [\App\Http\Controllers\DirectoryController::class, 'testConnection'])->name('directory.test-connection')->whereNumber('id');
            Route::delete('/directory/{id}', [\App\Http\Controllers\DirectoryController::class, 'destroy'])->name('directory.destroy')->whereNumber('id');

            // Legacy /billing module retired — replaced by Billing & Claims Audit
            Route::get('/billing', fn () => abort(404))->name('billing.index');
            Route::post('/billing/run', fn () => abort(404))->name('billing.run');
            Route::get('/billing/{id}', fn () => abort(404))->whereNumber('id')->name('billing.show');

            Route::get('/billing-claims-audit', [\App\Http\Controllers\BillingClaimsAuditController::class, 'index'])->name('billing-claims-audit.index')->middleware('permission:view_billing_claims_audit');
            Route::get('/billing-claims-audit/aging', [\App\Http\Controllers\BillingClaimsAuditController::class, 'aging'])->name('billing-claims-audit.aging')->middleware('permission:view_billing_claims_audit');
            Route::get('/billing-claims-audit/aging/export', [\App\Http\Controllers\BillingClaimsAuditController::class, 'exportAging'])->name('billing-claims-audit.aging.export')->middleware('permission:view_billing_claims_audit');
            Route::get('/billing-claims-audit/export', [\App\Http\Controllers\BillingClaimsAuditController::class, 'export'])->name('billing-claims-audit.export')->middleware('permission:view_billing_claims_audit');
            Route::post('/billing-claims-audit/generate-submit', [\App\Http\Controllers\BillingClaimsAuditController::class, 'generateSubmit'])->name('billing-claims-audit.generate-submit')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/submit', [\App\Http\Controllers\BillingClaimsAuditController::class, 'submitClaim'])->name('billing-claims-audit.submit')->middleware('permission:edit_billing_claims_audit');
            Route::get('/billing-claims-audit/{billing_claims_audit}/sigma-portal', [\App\Http\Controllers\BillingClaimsAuditController::class, 'sigmaPortal'])->name('billing-claims-audit.sigma-portal')->middleware('permission:view_billing_claims_audit');
            Route::post('/billing-claims-audit/refresh-availity-status', [\App\Http\Controllers\BillingClaimsAuditController::class, 'refreshAvailityStatusBatch'])->name('billing-claims-audit.refresh-availity-status')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/chase-overdue', [\App\Http\Controllers\BillingClaimsAuditController::class, 'chaseOverdue'])->name('billing-claims-audit.chase-overdue')->middleware('permission:edit_billing_claims_audit');
            Route::get('/billing-claims-audit/{billing_claims_audit}/pdf', [\App\Http\Controllers\BillingClaimsAuditController::class, 'downloadPdf'])->name('billing-claims-audit.pdf.download')->middleware('permission:view_billing_claims_audit');
            Route::get('/billing-claims-audit/{billing_claims_audit}/documents/{documentIndex}', [\App\Http\Controllers\BillingClaimsAuditController::class, 'downloadDocument'])->name('billing-claims-audit.documents.download')->middleware('permission:view_billing_claims_audit')->whereNumber('documentIndex');
            Route::get('/billing-claims-audit/{billing_claims_audit}/eob', [\App\Http\Controllers\BillingClaimsAuditController::class, 'downloadEob'])->name('billing-claims-audit.eob.download')->middleware('permission:view_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/escalate', [\App\Http\Controllers\BillingClaimsAuditController::class, 'escalate'])->name('billing-claims-audit.escalate')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/refresh', [\App\Http\Controllers\BillingClaimsAuditController::class, 'refresh'])->name('billing-claims-audit.refresh')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/refresh-availity-status', [\App\Http\Controllers\BillingClaimsAuditController::class, 'refreshAvailityStatus'])->name('billing-claims-audit.refresh-availity-status.claim')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/record-eob', [\App\Http\Controllers\BillingClaimsAuditController::class, 'recordEob'])->name('billing-claims-audit.record-eob')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/verify-eligibility', [\App\Http\Controllers\BillingClaimsAuditController::class, 'verifyEligibility'])->name('billing-claims-audit.verify-eligibility')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/unverify-eligibility', [\App\Http\Controllers\BillingClaimsAuditController::class, 'unverifyEligibility'])->name('billing-claims-audit.unverify-eligibility')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/mark-submitted', [\App\Http\Controllers\BillingClaimsAuditController::class, 'markSubmitted'])->name('billing-claims-audit.mark-submitted')->middleware('permission:edit_billing_claims_audit');
            Route::post('/billing-claims-audit/{billing_claims_audit}/override', [\App\Http\Controllers\BillingClaimsAuditController::class, 'override'])->name('billing-claims-audit.override')->middleware('permission:override_billing_claims_audit');
            Route::get('/billing-claims-audit/{billing_claims_audit}', [\App\Http\Controllers\BillingClaimsAuditController::class, 'show'])->name('billing-claims-audit.show')->middleware('permission:view_billing_claims_audit');
            Route::patch('/billing-claims-audit/{billing_claims_audit}/rate', [\App\Http\Controllers\BillingClaimsAuditController::class, 'updateRate'])->name('billing-claims-audit.update-rate')->middleware('permission:edit_billing_claims_audit');
            Route::put('/billing-claims-audit/{billing_claims_audit}', [\App\Http\Controllers\BillingClaimsAuditController::class, 'update'])->name('billing-claims-audit.update')->middleware('permission:edit_billing_claims_audit');

            Route::get('/payroll', [\App\Http\Controllers\PayrollController::class, 'index'])->name('payroll')->middleware('permission:view_payroll');
            Route::get('/payroll/export', [\App\Http\Controllers\PayrollController::class, 'export'])->name('payroll.export')->middleware('permission:export_payroll');
            Route::post('/payroll/build-batch', [\App\Http\Controllers\PayrollController::class, 'buildBatch'])->name('payroll.build-batch')->middleware('permission:run_payroll');
            Route::get('/payroll/batch-queue', [\App\Http\Controllers\PayrollController::class, 'batchQueue'])->name('payroll.batch-queue')->middleware('permission:view_payroll');
            Route::post('/payroll/batches/{batch}/approve', [\App\Http\Controllers\PayrollController::class, 'approveBatch'])->name('payroll.batch.approve')->middleware('permission:run_payroll');
            Route::get('/payroll/batches/{batch}/export', [\App\Http\Controllers\PayrollController::class, 'exportBatch'])->name('payroll.batch.export')->middleware('permission:export_payroll');
            Route::post('/payroll/accountants-world/employee', [\App\Http\Controllers\PayrollController::class, 'createAccountantsWorldEmployee'])->name('payroll.aw.create-employee')->middleware('permission:run_payroll');
            Route::post('/payroll/accountants-world/employee/{employee}/retry', [\App\Http\Controllers\PayrollController::class, 'retryAccountantsWorldEmployee'])->name('payroll.aw.retry-employee')->middleware('permission:run_payroll');
            Route::post('/payroll/accountants-world/employee/{employee}/resolve', [\App\Http\Controllers\PayrollController::class, 'resolveAccountantsWorldEmployee'])->name('payroll.aw.resolve-employee')->middleware('permission:run_payroll');
            Route::get('/payroll/{payRecord}/stub', [\App\Http\Controllers\PayrollController::class, 'downloadStub'])->name('payroll.stub')->middleware('permission:view_payroll');
            Route::get('/payroll/{payRecord}', [\App\Http\Controllers\PayrollController::class, 'show'])->name('payroll.show')->middleware('permission:view_payroll');
            Route::patch('/payroll/{payRecord}/wage', [\App\Http\Controllers\PayrollController::class, 'updateWage'])->name('payroll.update-wage')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/mark-processed', [\App\Http\Controllers\PayrollController::class, 'markProcessed'])->name('payroll.mark-processed')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/pay-stub', [\App\Http\Controllers\PayrollController::class, 'savePayStub'])->name('payroll.pay-stub')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/supplemental', [\App\Http\Controllers\PayrollController::class, 'addSupplemental'])->name('payroll.supplemental')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/reversal', [\App\Http\Controllers\PayrollController::class, 'addReversal'])->name('payroll.reversal')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/reversal-recovered', [\App\Http\Controllers\PayrollController::class, 'markReversalRecovered'])->name('payroll.reversal-recovered')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/hold', [\App\Http\Controllers\PayrollController::class, 'applyHold'])->name('payroll.apply-hold')->middleware('permission:edit_payroll');
            Route::post('/payroll/{payRecord}/release-hold', [\App\Http\Controllers\PayrollController::class, 'releaseHold'])->name('payroll.release-hold')->middleware('permission:release_payroll_hold');

            Route::get('/compliance', [\App\Http\Controllers\ComplianceController::class, 'index'])->name('compliance');
            Route::get('/audit-view', [\App\Http\Controllers\ComplianceController::class, 'auditIndex'])->name('audit-view');

            // Staff & AI Agents
            Route::get('/staff', [\App\Http\Controllers\StaffAiAgentsController::class, 'index'])->name('staff.index')->middleware('permission:view_staff');
            Route::get('/staff/agents/create', [\App\Http\Controllers\StaffAiAgentsController::class, 'createAgent'])->name('staff.agents.create')->middleware('permission:manage_ai_agents');
            Route::post('/staff/agents', [\App\Http\Controllers\StaffAiAgentsController::class, 'storeAgent'])->name('staff.agents.store')->middleware('permission:manage_ai_agents');
            Route::post('/staff/agents/import', [\App\Http\Controllers\StaffAiAgentsController::class, 'importAgents'])->name('staff.agents.import')->middleware('permission:manage_ai_agents');
            Route::get('/staff/agents/export', [\App\Http\Controllers\StaffAiAgentsController::class, 'exportAgents'])->name('staff.agents.export')->middleware('permission:view_staff');
            Route::get('/staff/agents/{slug}/export', [\App\Http\Controllers\StaffAiAgentsController::class, 'exportAgent'])->name('staff.agents.export.single')->middleware('permission:view_staff');
            Route::get('/staff/agents/{slug}', [\App\Http\Controllers\StaffAiAgentsController::class, 'showAgent'])->name('staff.agents.show')->middleware('permission:view_staff');
            Route::post('/staff/agents/{slug}', [\App\Http\Controllers\StaffAiAgentsController::class, 'updateAgent'])->name('staff.agents.update')->middleware('permission:edit_staff');
            Route::post('/staff/agents/{slug}/pause', [\App\Http\Controllers\StaffAiAgentsController::class, 'pauseAgent'])->name('staff.agents.pause')->middleware('permission:edit_staff');
            Route::post('/staff/agents/{slug}/enable', [\App\Http\Controllers\StaffAiAgentsController::class, 'toggleAgentEnabled'])->name('staff.agents.enable')->middleware('permission:manage_ai_agents');
            Route::post('/staff/agents/{slug}/token', [\App\Http\Controllers\StaffAiAgentsController::class, 'regenerateToken'])->name('staff.agents.token')->middleware('permission:manage_ai_agents');
            Route::delete('/staff/agents/{slug}', [\App\Http\Controllers\StaffAiAgentsController::class, 'destroyAgent'])->name('staff.agents.destroy')->middleware('permission:manage_ai_agents');

            // Staff user management
            Route::post('/staff', [\App\Http\Controllers\StaffController::class, 'store'])->name('staff.store')->middleware('permission:add_staff');
            Route::put('/staff/{id}', [\App\Http\Controllers\StaffController::class, 'update'])->name('staff.update')->whereNumber('id')->middleware('permission:edit_staff');
            Route::post('/staff/{id}/toggle', [\App\Http\Controllers\StaffController::class, 'toggleStatus'])->name('staff.toggle')->whereNumber('id')->middleware('permission:edit_staff');
            Route::get('/staff/create', [\App\Http\Controllers\StaffController::class, 'create'])->name('staff.create')->middleware('permission:add_staff');
            Route::get('/staff/{id}', [\App\Http\Controllers\StaffController::class, 'show'])->name('staff.show')->whereNumber('id')->middleware('permission:view_staff');
            Route::get('/staff/{id}/permissions', [\App\Http\Controllers\StaffController::class, 'permissions'])->name('staff.permissions')->whereNumber('id')->middleware('permission:manage_permissions');
            Route::post('/roles/{id}/permissions', [\App\Http\Controllers\StaffController::class, 'updateRolePermissions'])->name('roles.permissions.update')->middleware('permission:manage_permissions');
            Route::post('/staff/{id}/reset-password', [\App\Http\Controllers\StaffController::class, 'resetPassword'])->name('staff.reset-password')->whereNumber('id')->middleware('permission:edit_staff');
            Route::post('/staff/{id}/revoke-sessions', [\App\Http\Controllers\StaffController::class, 'revokeSessions'])->name('staff.revoke-sessions')->whereNumber('id')->middleware('permission:edit_staff');
        });

        // 3. Shared Routes (Anyone including Caregivers)
        Route::middleware(['role:Super Administrator,Administrator,Operations Staff,Employee'])->group(function () {
            Route::get('/schedule/export', [\App\Http\Controllers\ScheduleController::class, 'export'])->name('schedule.export')->middleware('permission:view_calendar');
            Route::get('/schedule/ical', [\App\Http\Controllers\ScheduleController::class, 'ical'])->name('schedule.ical')->middleware('permission:view_calendar');
            Route::get('/schedule/board', [\App\Http\Controllers\ScheduleController::class, 'board'])->name('schedule.board')->middleware('permission:view_calendar');
            Route::get('/schedule', [\App\Http\Controllers\ScheduleController::class, 'index'])->name('schedule.index')->middleware('permission:view_calendar');
            Route::get('/schedule/{id}', [\App\Http\Controllers\ScheduleController::class, 'show'])->name('schedule.show')->whereNumber('id')->middleware('permission:view_calendar');
            Route::post('/schedule', [\App\Http\Controllers\ScheduleController::class, 'store'])->name('schedule.store')->middleware('permission:manage_schedules');
            Route::post('/schedule/{id}/clock-in', [\App\Http\Controllers\ScheduleController::class, 'clockIn'])->name('schedule.clock-in');
            Route::post('/schedule/{id}/clock-out', [\App\Http\Controllers\ScheduleController::class, 'clockOut'])->name('schedule.clock-out');

            Route::get('/messages', [\App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
            Route::get('/messages/{id}', [\App\Http\Controllers\MessageController::class, 'show'])->name('messages.show');
            Route::post('/messages', [\App\Http\Controllers\MessageController::class, 'store'])->name('messages.store');

            Route::get('/search', [\App\Http\Controllers\SearchController::class, 'globalSearch'])->name('search.global');
            Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar')->middleware('permission:view_calendar');
            Route::post('/documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('documents.store');
            Route::post('/documents/signature', [\App\Http\Controllers\DocumentController::class, 'storeSignature'])->name('documents.signature.store');
            Route::get('/documents/{id}/download', [\App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');
            Route::post('/documents/{id}/verify', [\App\Http\Controllers\DocumentController::class, 'verify'])->name('documents.verify');
        });

        Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile');
        Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');

        Route::middleware(['demo.routes'])->group(function () {
            require __DIR__.'/demo.php';
        });

        Route::post('/location/switch', [\App\Http\Controllers\LocationController::class, 'switch'])->name('location.switch');

    });

// Public maintenance page (used when global maintenance mode is enabled)
Route::get('/maintenance', function () {
    return view('pages.maintenance', ['title' => 'Maintenance']);
})->name('maintenance');

// authentication pages


Route::get('/signin', [AuthController::class, 'showLoginForm'])->name('signin');
Route::post('/signin', [AuthController::class, 'login'])->name('signin.store');

Route::get('/signup', [AuthController::class, 'showRegistrationForm'])->name('signup');
Route::post('/signup', [AuthController::class, 'register'])->name('signup.store');

Route::get('/setup-account', [AuthController::class, 'showSetupForm'])->name('setup-account');
Route::post('/setup-account', [AuthController::class, 'storeSetup'])->name('setup-account.store');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');



Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

Route::get('/reset-password', function () {
    return redirect()->route('password.request');
})->name('reset-password');

Route::get('/two-factor/choice', [\App\Http\Controllers\TwoFactorController::class, 'showChoice'])->name('two-factor.choice')->middleware('auth');
Route::post('/two-factor/send', [\App\Http\Controllers\TwoFactorController::class, 'sendOTP'])->name('two-factor.send')->middleware('auth');
Route::post('/two-factor/resend', [\App\Http\Controllers\TwoFactorController::class, 'resendOTP'])->name('two-factor.resend')->middleware('auth');
Route::get('/two-factor/verify', [\App\Http\Controllers\TwoFactorController::class, 'showVerify'])->name('two-factor.verify')->middleware('auth');
Route::post('/two-factor/verify', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->name('two-factor.verify.post')->middleware('auth');

Route::get('/two-step-verification', function () {
    return redirect()->route('two-factor.verify');
})->name('two-step-verification');
