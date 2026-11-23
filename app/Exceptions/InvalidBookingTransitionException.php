<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Thrown when a booking cannot move to the requested status, e.g. approving a cancelled booking (HTTP 422). */
class InvalidBookingTransitionException extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
