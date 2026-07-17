<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Maps old seeded name → correct SSHC program name
    // IDs are preserved so existing client.coverage_type_id FKs stay valid.
    private array $renames = [
        'Medicaid'    => ['name' => 'DHS Home Help',    'plan_name' => 'DHS Home Help Program',             'description' => 'Michigan DHS Home Help (state-funded direct care)'],
        'Medicare'    => ['name' => 'MICH',             'plan_name' => 'MICH Program',                      'description' => 'MI Choice Waiver / Medicaid managed home care'],
        'Private Pay' => ['name' => 'ICO',              'plan_name' => 'Integrated Care Organization',      'description' => 'Medicare-Medicaid integrated care plan'],
        'Molina'      => ['name' => 'DAAA',             'plan_name' => 'Detroit AAA / Area Agency on Aging','description' => 'Area Agency on Aging — Older Michiganians Act'],
        'Blue Cross'  => ['name' => 'Private Pay',      'plan_name' => 'Self-Pay',                          'description' => 'Out-of-pocket / private payment'],
    ];

    public function up(): void
    {
        foreach ($this->renames as $old => $new) {
            DB::table('coverage_types')
                ->where('name', $old)
                ->update($new);
        }
    }

    public function down(): void
    {
        $reverse = array_combine(
            array_column($this->renames, 'name'),
            array_map(fn ($old, $new) => ['name' => $old, 'plan_name' => $new['plan_name']], array_keys($this->renames), $this->renames)
        );

        foreach ($reverse as $current => $old) {
            DB::table('coverage_types')
                ->where('name', $current)
                ->update(['name' => array_keys($this->renames)[array_search($current, array_column($this->renames, 'name'))]]);
        }
    }
};
