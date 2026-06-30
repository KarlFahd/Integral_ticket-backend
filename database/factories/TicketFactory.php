<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['hardware', 'software', 'network', 'account']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'status' => 'Open',
            'attachment' => null,
            'created_by' => $this->faker->userName(),
        ];
    }
}
