<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\FormsTrackingService;
use Illuminate\Console\Command;

class GenerateFormDraftsCommand extends Command
{
    protected $signature = 'forms:generate-drafts
        {--org= : Only generate drafts for one organization ID}';

    protected $description = 'Generate draft forms for compliance-required templates missing a current-month signed form';

    public function handle(FormsTrackingService $forms): int
    {
        $orgOption = $this->option('org');

        if ($orgOption) {
            $result = $forms->generateMissingComplianceDrafts((int) $orgOption);
            $this->lineResult((int) $orgOption, $result);

            return self::SUCCESS;
        }

        $orgIds = Organization::query()->pluck('id');

        if ($orgIds->isEmpty()) {
            $this->info('No organizations found.');

            return self::SUCCESS;
        }

        foreach ($orgIds as $orgId) {
            $result = $forms->generateMissingComplianceDrafts((int) $orgId);
            $this->lineResult((int) $orgId, $result);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{created: int, skipped: int, agent: string|null}  $result
     */
    private function lineResult(int $orgId, array $result): void
    {
        if (! $result['agent']) {
            $this->warn("Org {$orgId}: Forms agent unavailable — skipped.");

            return;
        }

        $this->info(sprintf(
            'Org %d: created %d draft(s), skipped %d.',
            $orgId,
            $result['created'],
            $result['skipped'],
        ));
    }
}
