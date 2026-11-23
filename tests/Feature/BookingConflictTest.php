<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private string $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->room = Room::factory()->create(['capacity' => 10]);
        $this->day = now()->addDays(3)->toDateString();

        // Existing approved booking: 10:00-11:00
        Booking::factory()->approved()->for($this->room)
            ->between("{$this->day} 10:00", "{$this->day} 11:00")->create();

        Sanctum::actingAs(User::factory()->create());
    }

    private function book(string $start, string $end, array $overrides = [])
    {
        return $this->postJson('/api/bookings', [
            'room_id' => $this->room->id,
            'title' => 'Team sync',
            'attendees' => 3,
            'start_time' => "{$this->day} {$start}",
            'end_time' => "{$this->day} {$end}",
            ...$overrides,
        ]);
    }

    public static function overlappingRanges(): array
    {
        return [
            'same slot' => ['10:00', '11:00'],
            'starts inside' => ['10:30', '11:30'],
            'ends inside' => ['09:30', '10:30'],
            'fully inside' => ['10:15', '10:45'],
            'fully covers' => ['09:00', '12:00'],
        ];
    }

    #[Test]
    #[DataProvider('overlappingRanges')]
    public function it_rejects_an_overlapping_booking(string $start, string $end): void
    {
        $this->book($start, $end)
            ->assertStatus(409)
            ->assertJsonCount(1, 'conflicts');

        $this->assertSame(1, Booking::count());
    }

    #[Test]
    public function back_to_back_bookings_do_not_conflict(): void
    {
        $this->book('09:00', '10:00')->assertCreated();
        $this->book('11:00', '12:00')->assertCreated();
    }

    #[Test]
    public function a_pending_booking_also_holds_the_slot(): void
    {
        $this->book('14:00', '15:00')->assertCreated()->assertJsonPath('status', 'pending');
        $this->book('14:30', '15:30')->assertStatus(409);
    }

    #[Test]
    public function rejected_and_cancelled_bookings_free_the_slot(): void
    {
        Booking::query()->update(['status' => BookingStatus::Cancelled]);

        $this->book('10:00', '11:00')->assertCreated();
    }

    #[Test]
    public function the_same_time_in_another_room_is_fine(): void
    {
        $other = Room::factory()->create();

        $this->book('10:00', '11:00', ['room_id' => $other->id])->assertCreated();
    }

    #[Test]
    public function the_availability_endpoint_reports_conflicts_without_booking(): void
    {
        $query = fn (string $s, string $e) => '/api/availability?'.http_build_query([
            'room_id' => $this->room->id, 'start_time' => "{$this->day} {$s}", 'end_time' => "{$this->day} {$e}",
        ]);

        $this->getJson($query('10:30', '11:30'))->assertOk()->assertJson(['available' => false])->assertJsonCount(1, 'conflicts');
        $this->getJson($query('11:00', '12:00'))->assertOk()->assertJson(['available' => true]);
    }

    #[Test]
    public function the_database_itself_refuses_overlaps_if_the_app_check_is_bypassed(): void
    {
        $this->expectException(QueryException::class);

        // Writing straight to the model skips BookingService, so only the
        // PostgreSQL EXCLUDE constraint stands in the way.
        Booking::factory()->for($this->room)->between("{$this->day} 10:30", "{$this->day} 11:30")->create();
    }

    #[Test]
    public function it_validates_time_rules_and_capacity(): void
    {
        $this->book('12:00', '11:00')->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->book('12:00', '12:05')->assertUnprocessable()->assertJsonValidationErrors('end_time');
        $this->book('12:00', '13:00', ['attendees' => 50])->assertUnprocessable()->assertJsonValidationErrors('attendees');

        $this->postJson('/api/bookings', [
            'room_id' => $this->room->id, 'title' => 'Past', 'attendees' => 1,
            'start_time' => now()->subDay()->toDateTimeString(), 'end_time' => now()->subDay()->addHour()->toDateTimeString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('start_time');
    }

    #[Test]
    public function inactive_rooms_cannot_be_booked(): void
    {
        $this->room->update(['is_active' => false]);

        $this->book('12:00', '13:00')->assertUnprocessable()->assertJsonValidationErrors('room_id');
    }

    #[Test]
    public function utc_timestamps_from_the_browser_are_stored_in_the_app_timezone(): void
    {
        // 03:00Z is 10:00 in Bangkok, which clashes with the 10:00-11:00 booking.
        $this->postJson('/api/bookings', [
            'room_id' => $this->room->id, 'title' => 'UTC client', 'attendees' => 1,
            'start_time' => "{$this->day}T03:30:00.000Z", 'end_time' => "{$this->day}T04:30:00.000Z",
        ])->assertStatus(409);

        $this->postJson('/api/bookings', [
            'room_id' => $this->room->id, 'title' => 'UTC client', 'attendees' => 1,
            'start_time' => "{$this->day}T05:00:00.000Z", 'end_time' => "{$this->day}T06:00:00.000Z",
        ])->assertCreated()->assertJsonPath('start_time', "{$this->day}T12:00:00+07:00");
    }
}
