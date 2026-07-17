<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Intake;

class SearchController extends Controller
{
    public function globalSearch(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return redirect()->back();
        }

        $clients = Client::where('first_name', 'LIKE', "%{$query}%")
            ->orWhere('last_name', 'LIKE', "%{$query}%")
            ->orWhere('member_id', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->limit(10)->get();

        $employees = Employee::where('first_name', 'LIKE', "%{$query}%")
            ->orWhere('last_name', 'LIKE', "%{$query}%")
            ->orWhere('ssn', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->limit(10)->get();

        $intakes = Intake::where('first_name', 'LIKE', "%{$query}%")
            ->orWhere('last_name', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->limit(10)->get();

        return view('pages.search.results', compact('clients', 'employees', 'intakes', 'query'), [
            'title' => "Search Results for '$query'"
        ]);
    }
}
