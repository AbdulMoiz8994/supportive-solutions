<?php

use App\Services\ScheduleClockService;
use Carbon\Carbon;

test('schedule clock service calculates worked hours safely', function () {
    $service = app(ScheduleClockService::class);

    $clockIn = Carbon::parse('2026-06-15 08:00:00');
    $clockOut = Carbon::parse('2026-06-15 12:30:00');

    expect($service->calculateWorkedHours($clockIn, $clockOut))->toBe(4.5);
});

test('schedule clock service returns zero for invalid intervals', function () {
    $service = app(ScheduleClockService::class);

    $clockIn = Carbon::parse('2026-06-15 12:00:00');
    $clockOut = Carbon::parse('2026-06-15 08:00:00');

    expect($service->calculateWorkedHours($clockIn, $clockOut))->toBe(0.0);
});
