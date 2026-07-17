<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('schedule.index', array_merge($request->query(), [
            'view' => 'month',
        ]));
    }
}
