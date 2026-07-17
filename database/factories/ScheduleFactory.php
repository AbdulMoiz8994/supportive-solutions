<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+1 month');
        $start = (clone $date)->setTime(9, 0);
        $end = (clone $date)->setTime(13, 0);

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'event_type' => fake()->randomElement(Schedule::eventTypes()),
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i:s'),
            'end_time' => $end->format('H:i:s'),
            'start_at' => $start,
            'end_at' => $end,
            'timezone' => config('app.timezone', 'UTC'),
            'address' => fake()->optional()->streetAddress(),
            'all_day' => false,
            'status' => Schedule::STATUS_SCHEDULED,
            'evv_status' => false,
        ];
    }

    public function careVisit(): static
    {
        return $this->state(fn () => [
            'event_type' => Schedule::EVENT_CARE_VISIT,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn () => [
            'event_type' => Schedule::EVENT_INTERNAL,
            'client_id' => null,
            'employee_id' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Schedule::STATUS_CANCELLED,
        ]);
    }
}
