<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;

class BookingReviewController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function approve(ReviewBookingRequest $request, Booking $booking): BookingResource
    {
        $booking = $this->bookings->approve($booking, $request->user(), $request->input('note'));

        return new BookingResource($booking->load('room', 'user', 'reviewer'));
    }

    public function reject(ReviewBookingRequest $request, Booking $booking): BookingResource
    {
        $booking = $this->bookings->reject($booking, $request->user(), $request->input('note'));

        return new BookingResource($booking->load('room', 'user', 'reviewer'));
    }
}
