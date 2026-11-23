<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['room_id', 'user_id', 'title', 'description', 'attendees', 'start_time', 'end_time', 'status'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'reviewed_at' => 'datetime',
            'status' => BookingStatus::class,
            'attendees' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Bookings that currently hold their time slot (pending or approved). */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', BookingStatus::activeValues());
    }

    /**
     * Two half-open ranges [a1, a2) and [b1, b2) overlap exactly when a1 < b2 AND b1 < a2.
     * Back-to-back bookings (10:00-11:00 then 11:00-12:00) therefore do not conflict.
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->where('start_time', '<', $end)->where('end_time', '>', $start);
    }

    public function isCancellable(): bool
    {
        return $this->status->isActive() && $this->end_time->isFuture();
    }
}
