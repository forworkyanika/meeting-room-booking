<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Feeds FullCalendar. FullCalendar asks for ?start=...&end=... of the visible range
 * and expects an array of { id, title, start, end, ... } event objects back.
 */
class CalendarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after:start'],
            'room_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $events = Booking::query()
            ->with(['room', 'user'])
            ->active()
            ->overlapping(Carbon::parse($request->input('start')), Carbon::parse($request->input('end')))
            ->when($request->integer('room_id'), fn ($q, $roomId) => $q->where('room_id', $roomId))
            ->orderBy('start_time')
            ->get()
            ->map(function (Booking $booking) use ($user) {
                $isPending = $booking->status === BookingStatus::Pending;

                return [
                    'id' => (string) $booking->id,
                    'title' => $booking->title,
                    'start' => $booking->start_time->toIso8601String(),
                    'end' => $booking->end_time->toIso8601String(),
                    'backgroundColor' => $isPending ? '#ffffff' : $booking->room->color,
                    'borderColor' => $booking->room->color,
                    'textColor' => $isPending ? $booking->room->color : '#ffffff',
                    'classNames' => $isPending ? ['event-pending'] : ['event-approved'],
                    'extendedProps' => [
                        'status' => $booking->status->value,
                        'room' => $booking->room->name,
                        'room_id' => $booking->room_id,
                        'booked_by' => $booking->user->name,
                        'attendees' => $booking->attendees,
                        'is_mine' => $booking->user_id === $user->id,
                    ],
                ];
            });

        return response()->json($events);
    }
}
