<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 14))->setTime(fake()->numberBetween(8, 16), 0);

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'attendees' => fake()->numberBetween(1, 4),
            'start_time' => $start,
            'end_time' => $start->copy()->addHour(),
            'status' => BookingStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Approved]);
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn () => ['start_time' => $start, 'end_time' => $end]);
    }
}
