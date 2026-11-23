<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->employee = User::factory()->create();
    }

    #[Test]
    public function an_admin_can_approve_a_pending_booking(): void
    {
        $booking = Booking::factory()->for($this->employee)->create();
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/bookings/{$booking->id}/approve", ['note' => 'Enjoy'])
            ->assertOk()
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('reviewer.id', $this->admin->id);

        $this->assertNotNull($booking->fresh()->reviewed_at);
    }

    #[Test]
    public function rejecting_requires_a_reason(): void
    {
        $booking = Booking::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/bookings/{$booking->id}/reject")->assertUnprocessable()->assertJsonValidationErrors('note');

        $this->patchJson("/api/admin/bookings/{$booking->id}/reject", ['note' => 'Room under maintenance'])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('admin_note', 'Room under maintenance');
    }

    #[Test]
    public function only_pending_bookings_can_be_reviewed(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Cancelled]);
        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/bookings/{$booking->id}/approve")->assertUnprocessable();
    }

    #[Test]
    public function employees_cannot_use_admin_endpoints(): void
    {
        $booking = Booking::factory()->create();
        Sanctum::actingAs($this->employee);

        $this->patchJson("/api/admin/bookings/{$booking->id}/approve")->assertForbidden();
        $this->postJson('/api/admin/rooms', ['name' => 'X', 'capacity' => 4])->assertForbidden();
        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    #[Test]
    public function an_admins_own_booking_is_approved_immediately(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/bookings', [
            'room_id' => $room->id, 'title' => 'Board meeting', 'attendees' => 2,
            'start_time' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
            'end_time' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        ])->assertCreated()->assertJsonPath('status', 'approved');
    }

    #[Test]
    public function a_user_can_cancel_their_own_upcoming_booking_but_not_someone_elses(): void
    {
        $mine = Booking::factory()->for($this->employee)->create();
        $theirs = Booking::factory()->create();
        Sanctum::actingAs($this->employee);

        $this->patchJson("/api/bookings/{$theirs->id}/cancel")->assertForbidden();
        $this->patchJson("/api/bookings/{$mine->id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->patchJson("/api/bookings/{$mine->id}/cancel")->assertForbidden();
    }

    #[Test]
    public function users_only_see_their_own_bookings_unless_admin(): void
    {
        Booking::factory()->for($this->employee)->count(2)->create();
        Booking::factory()->count(3)->create();

        Sanctum::actingAs($this->employee);
        $this->getJson('/api/bookings?scope=all')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/bookings?scope=all')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/bookings?scope=all&status=pending')->assertOk()->assertJsonCount(5, 'data');
    }

    #[Test]
    public function rooms_with_history_are_deactivated_not_deleted(): void
    {
        $booking = Booking::factory()->create();
        $empty = Room::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/admin/rooms/{$booking->room_id}")->assertStatus(409);
        $this->deleteJson("/api/admin/rooms/{$empty->id}")->assertNoContent();
    }
}
