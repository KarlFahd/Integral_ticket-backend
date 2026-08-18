<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 hour', '+2 weeks');

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'type_id' => EventType::inRandomOrder()->value('id'),
            'event_date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}
