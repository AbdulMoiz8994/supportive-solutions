<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function switch(Request $request)
    {
        $locationId = $request->input('location_id');
        
        if ($locationId === 'all') {
            session(['selected_location_id' => null]);
            session(['selected_location_name' => 'Company Wide']);
            return back()->with('success', "Switched to Company Wide context.");
        }

        $location = Location::findOrFail($locationId);
        
        session(['selected_location_id' => $location->id]);
        session(['selected_location_name' => $location->name]);
        
        return back()->with('success', "Switched to {$location->name} context.");
    }
}
