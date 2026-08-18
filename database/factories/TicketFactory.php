<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Priority;
use App\Models\Status;
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
            'category_id' => Category::inRandomOrder()->value('id'),
            'priority_id' => Priority::inRandomOrder()->value('id'),
            'status_id' => Status::where('name', 'Open')->value('id'),
            'attachment' => null,
            'created_by' => $this->faker->userName(),
        ];
    }
}
