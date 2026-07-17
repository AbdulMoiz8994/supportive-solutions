<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\CoverageType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only audit of why the Program column may show "—". A client renders "—"
 * only when its coverage type can't resolve (no coverage_type_id, or a
 * coverage_type_id that points at a missing coverage_types row). Run this on any
 * environment to see instantly whether blank Programs are a data or code problem.
 *
 *   php artisan clients:program-audit
 */
class ClientProgramAuditCommand extends Command
{
    protected $signature = 'clients:program-audit {--sample=10 : How many blank-program clients to list}';

    protected $description = 'Audit clients whose Program renders blank ("—") and why';

    public function handle(): int
    {
        $clients = Client::withoutGlobalScopes()->with('coverageType')->get();
        $total = $clients->count();

        $validIds = CoverageType::query()->pluck('name', 'id');

        $nullFk = $clients->whereNull('coverage_type_id');
        $orphaned = $clients->filter(fn (Client $c) => $c->coverage_type_id !== null && ! $validIds->has($c->coverage_type_id));
        $blank = $clients->filter(fn (Client $c) => $c->program_display === '—');

        $this->info("Client Program audit — {$total} clients");
        $this->line('');

        $this->table(['Check', 'Count', 'Meaning'], [
            ['Program renders "—"', $blank->count(), 'What the list shows blank'],
            ['  ↳ no coverage_type_id', $nullFk->count(), 'DATA: program never assigned'],
            ['  ↳ orphaned coverage_type_id', $orphaned->count(), 'DATA: points at a deleted coverage_types row'],
            ['Program resolves OK', $total - $blank->count(), 'Renders DHS/MICH/ICO/DAAA fine'],
        ]);

        $this->line('');
        $this->line('<comment>coverage_types on file:</comment> '.($validIds->isEmpty()
            ? 'NONE — the coverage_types table is empty (run the seeders/migrations)'
            : $validIds->map(fn ($n, $id) => "{$id}={$n}")->implode(', ')));

        $this->line('');
        $this->line('<comment>program_display distribution:</comment>');
        foreach ($clients->groupBy(fn (Client $c) => $c->program_display)->map->count()->sortDesc() as $label => $count) {
            $this->line('  '.str_pad($label, 14).$count);
        }

        if ($blank->isNotEmpty()) {
            $this->line('');
            $this->line('<comment>Sample blank-program clients:</comment>');
            foreach ($blank->take((int) $this->option('sample')) as $c) {
                $reason = $c->coverage_type_id === null ? 'no coverage_type_id' : "orphaned id={$c->coverage_type_id}";
                $this->line("  #{$c->id}  ".trim($c->first_name.' '.$c->last_name).'  ('.$reason.')');
            }
            $this->line('');
            $this->line('<info>Fix:</info> assign each client a Program (coverage type) on their record, or bulk-set via the DB. The list/table code is correct — it renders the Program as soon as the data resolves.');
        }

        return self::SUCCESS;
    }
}
