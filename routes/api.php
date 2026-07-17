<?php

use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\ComplianceFormController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\EarningsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PayController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RealtimeController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\VisitTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (caregiver app)
|--------------------------------------------------------------------------
| Token auth via Laravel Sanctum. Flow:
|   1. POST /api/login            -> returns { token }
|   2. send header on every call: Authorization: Bearer <token>
|
| All authenticated endpoints below act on the LOGGED-IN caregiver only.
| See docs/MOBILE_API.md for the full contract.
*/

Route::post('/login', [AuthController::class, 'login']);

Route::post('/webhooks/ringcentral', [\App\Http\Controllers\Api\CommunicationWebhookController::class, 'ringCentral'])
    ->name('webhooks.ringcentral');

Route::post('/webhooks/retell', [\App\Http\Controllers\Api\CommunicationWebhookController::class, 'retell'])
    ->name('webhooks.retell');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('api.refresh');

    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/me', [ProfileController::class, 'show'])->name('api.me');

    // Home screen ("Welcome Back") aggregate
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('api.dashboard');

    // Assigned clients (pick-list for clocking in)
    Route::get('/assignments', [AssignmentController::class, 'index'])->name('api.assignments');

    // Schedule / shifts (Day list + Week view)
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('api.schedule');
    Route::get('/schedule/week', [ScheduleController::class, 'week'])->name('api.schedule.week');

    // Clock in / clock out (visits) — this platform is the source of truth
    Route::get('/visits/active', [VisitController::class, 'active'])->name('api.visits.active');
    Route::post('/visits/clock-in', [VisitController::class, 'clockIn'])->name('api.visits.clock-in');
    Route::post('/visits/clock-out', [VisitController::class, 'clockOut'])->name('api.visits.clock-out');
    Route::get('/visits', [VisitController::class, 'index'])->name('api.visits.index');

    // Care-task checklist on a visit
    Route::get('/visits/{schedule}/tasks', [VisitTaskController::class, 'index'])->name('api.visits.tasks.index');
    Route::post('/visits/{schedule}/tasks', [VisitTaskController::class, 'store'])->name('api.visits.tasks.store');
    Route::post('/visits/{schedule}/tasks/{task}/toggle', [VisitTaskController::class, 'toggle'])->name('api.visits.tasks.toggle');

    // Pay history + detail + stub download
    Route::get('/pay', [PayController::class, 'index'])->name('api.pay.index');
    Route::get('/pay/{payRecord}/stub', [PayController::class, 'stub'])->name('api.pay.stub');
    Route::get('/pay/{payRecord}', [PayController::class, 'show'])->name('api.pay.show');

    // Earnings & hours (Payroll screen graphs + YTD)
    Route::get('/earnings/summary', [EarningsController::class, 'summary'])->name('api.earnings.summary');

    // Monthly compliance certification
    Route::get('/compliance-forms', [ComplianceFormController::class, 'index'])->name('api.compliance.index');
    Route::get('/compliance-forms/history', [ComplianceFormController::class, 'history'])->name('api.compliance.history');
    Route::get('/compliance-forms/{complianceForm}', [ComplianceFormController::class, 'show'])->name('api.compliance.show');
    Route::post('/compliance-forms/{complianceForm}/submit', [ComplianceFormController::class, 'submit'])->name('api.compliance.submit');

    // Document capture → EMR
    Route::get('/documents', [DocumentController::class, 'index'])->name('api.documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('api.documents.store');

    // Click-to-call ("Call Now" on My Clients) + call history
    Route::get('/calls', [CallController::class, 'index'])->name('api.calls.index');
    Route::post('/calls', [CallController::class, 'store'])->name('api.calls.store');

    // Notifications (in-app alert feed)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('api.notifications.unread-count');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('api.notifications.destroy');

    // Real-time socket diagnostics (verify Reverb key/host match — fixes the 4001 connect/disconnect loop)
    Route::get('/realtime/config', [RealtimeController::class, 'config'])->name('api.realtime.config');

    // Chat / messaging (secure message threads)
    Route::get('/conversations', [ConversationController::class, 'index'])->name('api.conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('api.conversations.store');
    Route::get('/conversations/unread-count', [ConversationController::class, 'unreadCount'])->name('api.conversations.unread-count');
    Route::get('/conversations/{thread}', [ConversationController::class, 'show'])->name('api.conversations.show');
    Route::post('/conversations/{thread}/messages', [ConversationController::class, 'sendMessage'])->name('api.conversations.messages.store');
});
