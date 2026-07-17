<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::with(['statusRecord'])->latest()->get();
        $employeeStatuses = \App\Models\Status::where('entity_type', 'Employee')->get();

        return view('pages.employees.index', compact('employees', 'employeeStatuses'), ['title' => 'Employee Management']);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();
        $statusId = $validated['status_id'] ?? null;
        $status = $statusId ? \App\Models\Status::find($statusId) : \App\Models\Status::where('entity_type', 'Employee')->where('name', 'Active')->first();

        $location = session('selected_location', 'Michigan');
        if ($location === 'Company Wide') {
            $location = 'Michigan';
        }

        Employee::create(array_merge($validated, [
            'organization_id' => auth()->user()->organization_id ?? \App\Models\Organization::first()?->id ?? 1,
            'status_id' => $status?->id,
            'status' => $status?->name ?? 'Active',
            'office_location' => $location,
            'champs_password' => 'secret',
        ]));

        return redirect()->route('employees.index')->with('success', 'Employee registered successfully.');
    }

    public function show($id)
    {
        $employee = Employee::withoutGlobalScopes()->with([
            'user',
            'statusRecord',
            'clients',
            'documents',
            'schedules.client',
        ])->findOrFail($id);

        $this->authorize('view', $employee);

        return view('pages.employees.show', compact('employee'), ['title' => 'Employee Details']);
    }

    public function update(UpdateEmployeeRequest $request, $id)
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($id);
        $validated = $request->validated();

        if (isset($validated['status_id'])) {
            $status = \App\Models\Status::find($validated['status_id']);
            $validated['status'] = $status?->name;
        }

        $employee->update($validated);

        return redirect()->route('employees.index')->with('success', 'Employee record updated successfully.');
    }

    public function destroy($id)
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($id);
        $this->authorize('delete', $employee);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'Employee record removed.');
    }
}
