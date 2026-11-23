<?php

namespace App\Exceptions;

use App\Http\Resources\BookingResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Thrown when a booking would overlap an active booking in the same room (HTTP 409). */
class BookingConflictException extends RuntimeException
{
    public function __construct(public readonly Collection $conflicts)
    {
        parent::__construct('This room is already booked for part of the selected time.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'conflicts' => BookingResource::collection($this->conflicts->load('room', 'user')),
        ], 409);
    }
}
