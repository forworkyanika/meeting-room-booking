<?php

namespace App\Http\Resources;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'capacity' => $this->capacity,
            'description' => $this->description,
            'amenities' => $this->amenities ?? [],
            'color' => $this->color,
            'is_active' => $this->is_active,
            'upcoming_bookings_count' => $this->whenCounted('bookings'),
        ];
    }
}
