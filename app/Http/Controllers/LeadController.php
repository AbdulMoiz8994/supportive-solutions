<?php

namespace App\Http\Controllers;

use App\Models\Intake;
use App\Models\Status;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index()
    {
        $leads = Intake::latest()->get();
        return view('pages.leads.index', compact('leads'), ['title' => 'Leads Management']);
    }

    public function show($id)
    {
        $lead = Intake::findOrFail($id);
        return view('pages.leads.show', compact('lead'), ['title' => 'Edit Lead']);
    }

    public function update(Request $request, $id)
    {
        $lead = Intake::findOrFail($id);
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'nullable|email',
            'phone'      => 'nullable|string',
            'dob'        => 'nullable|date',
            'source'     => 'nullable|string',
            'status'     => 'nullable|string',
            'notes'      => 'nullable|string',
            'id_expiry'  => 'nullable|date',
            'champs_association_date' => 'nullable|date',
            'scan_id'    => 'nullable|string',
        ]);

        $lead->update($validated);

        return redirect()->route('leads.index')->with('success', 'Lead updated successfully!');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'nullable|email',
            'phone'      => 'nullable|string',
            'dob'        => 'nullable|date',
            'source'     => 'nullable|string',
        ]);

        $status = Status::where('entity_type', 'Intake')->where('name', 'New Lead')->first();

        Intake::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id ?? \App\Models\Organization::first()?->id ?? 1,
            'status_id' => $status?->id,
            'status' => 'New',
        ]));

        return redirect()->route('leads.index')->with('success', 'New lead created successfully!');
    }
}
