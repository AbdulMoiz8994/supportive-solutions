<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\RunBillingCycleRequest;
use App\Models\Billing;
use App\Services\BillingCycleService;
use App\Services\GlobalSettingsService;

class BillingController extends Controller
{
    public function __construct(
        protected GlobalSettingsService $settingsService,
        protected BillingCycleService $billingCycleService
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Billing::class);

        $billings = Billing::with(['client'])
            ->where('organization_id', auth()->user()->organization_id)
            ->latest()
            ->paginate(15);

        return view('pages.billing.index', [
            'billings' => $billings,
            'defaultBillingCycle' => $this->settingsService->defaultBillingCycle(),
        ], ['title' => 'Billing & Invoicing']);
    }

    public function runGlobalCycle(RunBillingCycleRequest $request)
    {
        $orgId = auth()->user()->organization_id;
        $cycle = $this->billingCycleService->cycle();

        $schedules = \App\Models\Schedule::with('client')
            ->where('organization_id', $orgId)
            ->where('status', 'Completed')
            ->whereNull('billing_id')
            ->get();

        $schedules = $this->billingCycleService->filterSchedulesForCycle($schedules, $cycle);

        if ($schedules->isEmpty()) {
            return redirect()->back()->with('info', 'No new completed visits to bill for the current '.$cycle.' cycle.');
        }

        $grouped = $schedules->groupBy('client_id');
        $createdCount = 0;
        $prefix = $this->billingCycleService->invoicePrefix($cycle);

        foreach ($grouped as $clientId => $clientSchedules) {
            $client = $clientSchedules->first()->client;

            $billing = Billing::create([
                'organization_id' => $orgId,
                'client_id' => $clientId,
                'invoice_number' => $prefix.'-'.strtoupper(uniqid()),
                'period_start' => $clientSchedules->min('date'),
                'period_end' => $clientSchedules->max('date'),
                'total_amount' => $clientSchedules->sum('total_hours') * ($client->billing_rate ?? 25.00),
                'status' => 'Pending',
            ]);

            foreach ($clientSchedules as $sch) {
                $sch->update(['billing_id' => $billing->id]);
            }

            $createdCount++;
        }

        return redirect()->route('billing.index')->with('success', "Successfully generated $createdCount new invoices.");
    }

    public function show($id)
    {
        $billing = Billing::withoutGlobalScopes()->with(['client', 'schedules.employee'])->findOrFail($id);
        $this->authorize('view', $billing);

        return view('pages.billing.show', compact('billing'), ['title' => 'Invoice '.$billing->invoice_number]);
    }
}
