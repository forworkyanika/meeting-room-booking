<?php

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Room '.fake()->unique()->bothify('??-###'),
            'location' => 'Floor '.fake()->numberBetween(1, 10),
            'capacity' => fake()->numberBetween(4, 30),
            'description' => fake()->sentence(),
            'amenities' => fake()->randomElements(['Projector', 'TV', 'Whiteboard', 'Video conference', 'Speakerphone'], 2),
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
