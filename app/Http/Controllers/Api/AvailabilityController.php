<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Http\Resources\BookingResource;
use App\Services\BookingService;
use App\Support\AppTime;
use Illuminate\Http\JsonResponse;

/** Lets the booking form warn about a clash before the user presses "Book". */
class AvailabilityController extends Controller
{
    public function __invoke(CheckAvailabilityRequest $request, BookingService $bookings): JsonResponse
    {
        $conflicts = $bookings->findConflicts(
            $request->integer('room_id'),
            AppTime::parse($request->input('start_time')),
            AppTime::parse($request->input('end_time')),
            $request->integer('ignore_booking_id') ?: null,
        );

        return response()->json([
            'available' => $conflicts->isEmpty(),
            'conflicts' => BookingResource::collection($conflicts->load('room', 'user')),
        ]);
    }
}
