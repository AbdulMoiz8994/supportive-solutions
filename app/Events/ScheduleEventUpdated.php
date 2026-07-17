<?php

namespace App\Events;

use App\Models\Schedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScheduleEventUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Schedule $schedule) {}
}
