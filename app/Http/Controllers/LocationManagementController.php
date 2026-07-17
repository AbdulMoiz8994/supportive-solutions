<?php

namespace App\Http\Controllers;

use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\Request;

class LocationManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Location::class);

        $query = Location::withCount(['users', 'clients', 'employees']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('state', 'like', "%{$request->search}%")
                    ->orWhere('address', 'like', "%{$request->search}%");
            });
        }

        $locations = $query->latest()->paginate(15);

        return view('pages.locations.index', compact('locations'), ['title' => 'Location Settings']);
    }

    public function store(StoreLocationRequest $request)
    {
        $validated = $request->validated();

        Location::create([
            'name' => $validated['name'],
            'state' => $validated['state'],
            'address' => $validated['address'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('locations.index')->with('success', 'Location created successfully.');
    }

    public function update(UpdateLocationRequest $request, $id)
    {
        $location = Location::findOrFail($id);
        $validated = $request->validated();

        $location->update([
            'name' => $validated['name'],
            'state' => $validated['state'],
            'address' => $validated['address'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('locations.index')->with('success', 'Location updated successfully.');
    }

    public function destroy($id)
    {
        $location = Location::findOrFail($id);
        $this->authorize('delete', $location);

        if ($location->users()->count() > 0 || $location->clients()->count() > 0 || $location->employees()->count() > 0) {
            return redirect()->route('locations.index')->with('error', 'Cannot delete location as it has assigned staff, clients, or employees.');
        }

        $location->delete();

        return redirect()->route('locations.index')->with('success', 'Location deleted successfully.');
    }
}
