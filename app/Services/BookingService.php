<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * All booking state changes go through this class so the conflict rule and the
 * approval workflow live in one place instead of being spread over controllers.
 *
 *   pending ──approve──▶ approved
 *      │                    │
 *      ├──reject──▶ rejected│
 *      └──cancel──▶ cancelled ◀──cancel
 */
class BookingService
{
    /** PostgreSQL SQLSTATE for a violated EXCLUDE constraint. */
    private const EXCLUSION_VIOLATION = '23P01';

    /**
     * Active bookings in the room whose time range overlaps [start, end).
     */
    public function findConflicts(int $roomId, CarbonInterface $start, CarbonInterface $end, ?int $ignoreBookingId = null): Collection
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->active()
            ->overlapping($start, $end)
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  array{room_id:int, title:string, description?:?string, attendees?:int, start_time:string, end_time:string}  $data
     */
    public function create(User $user, array $data): Booking
    {
        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        return $this->guardAgainstRace(fn () => DB::transaction(function () use ($user, $data, $start, $end) {
            // Lock the room row so two concurrent requests for the same room run the
            // conflict check one after the other instead of both seeing "free".
            Room::query()->whereKey($data['room_id'])->lockForUpdate()->firstOrFail();

            $conflicts = $this->findConflicts($data['room_id'], $start, $end);
            if ($conflicts->isNotEmpty()) {
                throw new BookingConflictException($conflicts);
            }

            $booking = new Booking([
                ...$data,
                'start_time' => $start,
                'end_time' => $end,
                'user_id' => $user->id,
                // Admins booking for themselves skip the approval queue.
                'status' => $user->isAdmin() ? BookingStatus::Approved : BookingStatus::Pending,
            ]);

            if ($user->isAdmin()) {
                $booking->reviewed_by = $user->id;
                $booking->reviewed_at = now();
            }

            $booking->save();

            return $booking;
        }), $data['room_id'], $start, $end);
    }

    public function approve(Booking $booking, User $admin, ?string $note = null): Booking
    {
        $this->assertStatus($booking, BookingStatus::Pending, 'approve');

        return DB::transaction(function () use ($booking, $admin, $note) {
            Room::query()->whereKey($booking->room_id)->lockForUpdate()->first();

            $conflicts = $this->findConflicts($booking->room_id, $booking->start_time, $booking->end_time, $booking->id);
            if ($conflicts->isNotEmpty()) {
                throw new BookingConflictException($conflicts);
            }

            return $this->review($booking, BookingStatus::Approved, $admin, $note);
        });
    }

    public function reject(Booking $booking, User $admin, string $note): Booking
    {
        $this->assertStatus($booking, BookingStatus::Pending, 'reject');

        return $this->review($booking, BookingStatus::Rejected, $admin, $note);
    }

    public function cancel(Booking $booking): Booking
    {
        if (! $booking->isCancellable()) {
            throw new InvalidBookingTransitionException('Only upcoming pending or approved bookings can be cancelled.');
        }

        $booking->status = BookingStatus::Cancelled;
        $booking->save();

        return $booking;
    }

    private function review(Booking $booking, BookingStatus $status, User $admin, ?string $note): Booking
    {
        $booking->status = $status;
        $booking->admin_note = $note;
        $booking->reviewed_by = $admin->id;
        $booking->reviewed_at = now();
        $booking->save();

        return $booking;
    }

    private function assertStatus(Booking $booking, BookingStatus $expected, string $action): void
    {
        if ($booking->status !== $expected) {
            throw new InvalidBookingTransitionException(
                "Cannot {$action} a booking that is {$booking->status->value}."
            );
        }
    }

    /**
     * Translate the database exclusion-constraint error into the same 409 the
     * application-level check returns, so clients see one consistent response.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function guardAgainstRace(callable $callback, int $roomId, CarbonInterface $start, CarbonInterface $end): mixed
    {
        try {
            return $callback();
        } catch (QueryException $e) {
            if ($e->getCode() === self::EXCLUSION_VIOLATION) {
                throw new BookingConflictException($this->findConflicts($roomId, $start, $end));
            }

            throw $e;
        }
    }
}
