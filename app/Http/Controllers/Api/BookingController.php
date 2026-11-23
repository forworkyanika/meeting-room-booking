<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /**
     * The signed-in user's bookings. Admins may pass scope=all to see everyone's.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'scope' => ['nullable', Rule::in(['mine', 'all'])],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])],
            'when' => ['nullable', Rule::in(['upcoming', 'past'])],
            'room_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $showAll = $request->input('scope') === 'all' && $user->isAdmin();

        $bookings = Booking::query()
            ->with(['room', 'user', 'reviewer'])
            ->unless($showAll, fn ($q) => $q->where('user_id', $user->id))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('room_id'), fn ($q, $roomId) => $q->where('room_id', $roomId))
            ->when($request->input('when') === 'upcoming', fn ($q) => $q->where('end_time', '>=', now())->orderBy('start_time'))
            ->when($request->input('when') === 'past', fn ($q) => $q->where('end_time', '<', now())->orderByDesc('start_time'))
            ->when(! $request->input('when'), fn ($q) => $q->orderByDesc('start_time'))
            ->paginate($request->integer('per_page', 15));

        return BookingResource::collection($bookings);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->user(), $request->validated());

        return (new BookingResource($booking->load('room', 'user')))->response()->setStatusCode(201);
    }

    public function show(Booking $booking): BookingResource
    {
        Gate::authorize('view', $booking);

        return new BookingResource($booking->load('room', 'user', 'reviewer'));
    }

    public function cancel(Booking $booking): BookingResource
    {
        Gate::authorize('cancel', $booking);

        return new BookingResource($this->bookings->cancel($booking)->load('room', 'user'));
    }
}
