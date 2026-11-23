<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $includeInactive = $request->boolean('include_inactive') && $request->user()->isAdmin();

        $rooms = Room::query()
            ->unless($includeInactive, fn ($q) => $q->active())
            ->when($request->integer('min_capacity'), fn ($q, $min) => $q->where('capacity', '>=', $min))
            ->withCount(['bookings' => fn ($q) => $q->active()->where('end_time', '>', now())])
            ->orderBy('name')
            ->get();

        return RoomResource::collection($rooms);
    }

    public function show(Room $room): RoomResource
    {
        return new RoomResource($room);
    }

    public function store(RoomRequest $request): JsonResponse
    {
        return (new RoomResource(Room::create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(RoomRequest $request, Room $room): RoomResource
    {
        $room->update($request->validated());

        return new RoomResource($room);
    }

    public function destroy(Room $room): Response|JsonResponse
    {
        if ($room->bookings()->exists()) {
            return response()->json([
                'message' => 'This room has booking history. Deactivate it instead of deleting it.',
            ], 409);
        }

        $room->delete();

        return response()->noContent();
    }
}
