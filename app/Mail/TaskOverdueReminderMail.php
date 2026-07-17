<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskOverdueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{title: string, due_date: string, priority: string}>  $tasks
     */
    public function __construct(
        public string $recipientName,
        public array $tasks,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Overdue tasks need attention ('.count($this->tasks).')',
        );
    }

    public function content(): Content
    {
        $rows = collect($this->tasks)->map(function (array $task) {
            return '<li><strong>'.e($task['title']).'</strong> — due '.e($task['due_date'])
                .' · '.e(ucfirst($task['priority'])).' priority</li>';
        })->implode('');

        $tasksUrl = e(url('/tasks?status=overdue'));

        return new Content(
            htmlString: <<<HTML
                <p>Hello {$this->recipientName},</p>
                <p>The following tasks are overdue and need attention:</p>
                <ul>{$rows}</ul>
                <p><a href="{$tasksUrl}">Open Tasks</a></p>
            HTML
        );
    }
}
