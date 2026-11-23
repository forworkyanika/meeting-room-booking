<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'department' => 'Facilities',
        ]);

        $alice = User::factory()->create(['name' => 'Alice Employee', 'email' => 'user@example.com', 'department' => 'Engineering']);
        $bob = User::factory()->create(['name' => 'Bob Employee', 'email' => 'bob@example.com', 'department' => 'Sales']);

        $rooms = collect([
            ['name' => 'Everest', 'location' => 'Floor 8', 'capacity' => 20, 'color' => '#0d6efd', 'amenities' => ['Projector', 'Video conference', 'Whiteboard'], 'description' => 'Large boardroom for all-hands and client meetings.'],
            ['name' => 'Fuji', 'location' => 'Floor 8', 'capacity' => 8, 'color' => '#198754', 'amenities' => ['TV', 'Whiteboard'], 'description' => 'Mid-size room for team meetings.'],
            ['name' => 'Kilimanjaro', 'location' => 'Floor 5', 'capacity' => 12, 'color' => '#fd7e14', 'amenities' => ['Projector', 'Speakerphone'], 'description' => 'Training room with a projector.'],
            ['name' => 'Doi Inthanon', 'location' => 'Floor 5', 'capacity' => 4, 'color' => '#6f42c1', 'amenities' => ['TV', 'Video conference'], 'description' => 'Small huddle room for quick calls.'],
            ['name' => 'Alps', 'location' => 'Floor 3', 'capacity' => 6, 'color' => '#d63384', 'amenities' => ['Whiteboard'], 'description' => 'Brainstorming room with wall-to-wall whiteboards.'],
        ])->map(fn (array $room) => Room::create($room));

        Room::create(['name' => 'Old Storage Room', 'location' => 'Basement', 'capacity' => 4, 'color' => '#6c757d', 'is_active' => false, 'description' => 'Closed for renovation.']);

        $monday = now()->startOfWeek();
        $slot = fn (int $day, int $hour, int $hours = 1) => [
            'start_time' => $monday->copy()->addDays($day)->setTime($hour, 0),
            'end_time' => $monday->copy()->addDays($day)->setTime($hour + $hours, 0),
        ];

        $samples = [
            [$alice, 0, 'Sprint planning', 0, 9, 2, BookingStatus::Approved],
            [$bob, 1, 'Client call - ACME', 1, 10, 1, BookingStatus::Approved],
            [$alice, 2, 'Onboarding training', 2, 13, 3, BookingStatus::Approved],
            [$bob, 3, 'Sales sync', 3, 15, 1, BookingStatus::Pending],
            [$alice, 1, 'Design review', 4, 14, 1, BookingStatus::Pending],
            [$bob, 0, 'Quarterly review', 7, 9, 2, BookingStatus::Pending],
            [$alice, 4, '1:1 with manager', 8, 11, 1, BookingStatus::Approved],
            [$bob, 2, 'Product demo', 9, 10, 2, BookingStatus::Rejected],
        ];

        foreach ($samples as [$user, $roomIndex, $title, $day, $hour, $hours, $status]) {
            $booking = Booking::create([
                'room_id' => $rooms[$roomIndex]->id,
                'user_id' => $user->id,
                'title' => $title,
                'attendees' => min(4, $rooms[$roomIndex]->capacity),
                'status' => $status,
                ...$slot($day, $hour, $hours),
            ]);

            if ($status !== BookingStatus::Pending) {
                $booking->forceFill([
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'admin_note' => $status === BookingStatus::Rejected ? 'The room is reserved for maintenance that day.' : null,
                ])->save();
            }
        }
    }
}
