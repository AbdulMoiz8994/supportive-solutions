<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\GlobalSettingsService;
use Illuminate\Console\Command;

class DocumentRetentionReportCommand extends Command
{
    protected $signature = 'documents:retention-report {--json : Output as JSON}';

    protected $description = 'Report documents older than the configured retention period (non-destructive)';

    public function handle(GlobalSettingsService $settingsService): int
    {
        $retentionDays = $settingsService->documentRetentionDays();
        $cutoff = now()->subDays($retentionDays);

        $documents = Document::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->get(['id', 'name', 'organization_id', 'documentable_type', 'documentable_id', 'created_at']);

        if ($this->option('json')) {
            $this->line($documents->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("Document retention report (cutoff: {$cutoff->toDateString()}, {$retentionDays} days)");
        $this->info("Eligible for review: {$documents->count()} document(s)");
        $this->newLine();

        if ($documents->isEmpty()) {
            $this->comment('No documents exceed the configured retention period.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Organization', 'Subject Type', 'Subject ID', 'Created'],
            $documents->map(fn ($doc) => [
                $doc->id,
                $doc->name,
                $doc->organization_id,
                class_basename($doc->documentable_type),
                $doc->documentable_id,
                $doc->created_at?->toDateString(),
            ])
        );

        $this->newLine();
        $this->comment('This command is report-only. No files were deleted.');

        return self::SUCCESS;
    }
}
