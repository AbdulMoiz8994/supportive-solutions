<?php

namespace App\Console\Commands;

use App\Mail\TaskOverdueReminderMail;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskBoardStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueTaskRemindersCommand extends Command
{
    protected $signature = 'tasks:remind-overdue {--organization= : Limit to one organization id}';

    protected $description = 'Email assignees (or org admins for agent tasks) about overdue open tasks';

    public function handle(TaskBoardStatusService $boardStatuses): int
    {
        $orgId = $this->option('organization') ? (int) $this->option('organization') : null;

        $tasks = Task::query()
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->with(['assigneeUser', 'creator'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->get()
            ->filter(function (Task $task) use ($boardStatuses) {
                return ! $boardStatuses->isClosedStatus($task->organization_id, $task->status);
            });

        if ($tasks->isEmpty()) {
            $this->info('No overdue tasks found.');

            return self::SUCCESS;
        }

        $sent = 0;

        // Human-assigned: group by assignee and notify them.
        $tasks->filter(fn (Task $t) => $t->assignee_type === Task::ASSIGNEE_USER && $t->assignee_user_id)
            ->groupBy('assignee_user_id')
            ->each(function ($group, $userId) use (&$sent) {
                $user = $group->first()->assigneeUser;
                if (! $user?->email) {
                    return;
                }

                Mail::to($user->email)->send(new TaskOverdueReminderMail(
                    $user->name,
                    $this->taskSummaries($group),
                ));
                $sent++;
            });

        // Agent-assigned / unassigned: notify org admins with manage_tasks, else creators.
        $agentTasks = $tasks->filter(fn (Task $t) => $t->assignee_type !== Task::ASSIGNEE_USER || ! $t->assignee_user_id);

        $agentTasks->groupBy('organization_id')->each(function ($orgTasks, $organizationId) use (&$sent) {
            $recipients = $this->agentTaskRecipients((int) $organizationId, $orgTasks);

            foreach ($recipients as $user) {
                $relevant = $orgTasks->filter(function (Task $task) use ($user) {
                    return $user->hasPermission('manage_tasks')
                        || (int) $task->created_by === (int) $user->id;
                });

                if ($relevant->isEmpty() || ! $user->email) {
                    continue;
                }

                Mail::to($user->email)->send(new TaskOverdueReminderMail(
                    $user->name,
                    $this->taskSummaries($relevant),
                ));
                $sent++;
            }
        });

        $this->info("Sent {$sent} overdue task reminder email(s).");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Task>  $tasks
     * @return list<array{title: string, due_date: string, priority: string}>
     */
    private function taskSummaries($tasks): array
    {
        return $tasks->map(fn (Task $t) => [
            'title' => $t->title,
            'due_date' => $t->due_date?->format('M j, Y') ?? '—',
            'priority' => $t->priority,
        ])->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Task>  $orgTasks
     * @return list<User>
     */
    private function agentTaskRecipients(int $organizationId, $orgTasks): array
    {
        $admins = User::query()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u) => $u->hasPermission('manage_tasks'));

        if ($admins->isNotEmpty()) {
            return $admins->values()->all();
        }

        return $orgTasks
            ->pluck('creator')
            ->filter()
            ->unique('id')
            ->values()
            ->all();
    }
}
