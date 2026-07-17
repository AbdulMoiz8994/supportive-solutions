<?php

namespace App\Console\Commands;

use App\Models\DataExplorationView;
use App\Services\DataExplorationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class EmailScheduledDataExplorationViewsCommand extends Command
{
    protected $signature = 'data-exploration:email-scheduled-views';

    protected $description = 'Email CSV exports for saved data exploration views scheduled daily or weekly';

    public function handle(DataExplorationService $exploration): int
    {
        $views = DataExplorationView::query()
            ->with('user')
            ->whereIn('schedule_frequency', ['daily', 'weekly'])
            ->get()
            ->filter(fn (DataExplorationView $view) => $this->isDue($view));

        $sent = 0;

        foreach ($views as $view) {
            $user = $view->user;
            if (! $user?->email) {
                continue;
            }

            [$headers, $rows] = $exploration->exportCsv(
                $view->organization_id,
                $view->dataset,
                is_array($view->config) ? $view->config : [],
                $user,
            );

            $csv = $this->toCsvString($headers, $rows);
            $subject = 'Scheduled data exploration: '.$view->name;
            $filename = str($view->name)->slug().'-'.now()->format('Y-m-d').'.csv';

            Mail::html(
                '<p>Your scheduled data exploration export <strong>'.e($view->name).'</strong> is attached as CSV.</p>',
                function ($message) use ($user, $subject, $csv, $filename) {
                    $message->to($user->email, $user->name)
                        ->subject($subject)
                        ->attachData($csv, $filename, ['mime' => 'text/csv']);
                },
            );

            $view->update(['last_emailed_at' => now()]);
            $sent++;
        }

        $this->info("Emailed {$sent} scheduled data exploration view(s).");

        return self::SUCCESS;
    }

    private function isDue(DataExplorationView $view): bool
    {
        if (! $view->last_emailed_at) {
            return true;
        }

        return match ($view->schedule_frequency) {
            'daily' => $view->last_emailed_at->lt(today()),
            'weekly' => $view->last_emailed_at->lte(now()->subWeek()),
            default => false,
        };
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function toCsvString(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }
}
