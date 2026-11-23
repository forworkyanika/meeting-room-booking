<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * Statuses that hold a room's time slot. Rejected and cancelled bookings free it again.
     *
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Pending->value, self::Approved->value];
    }

    public function isActive(): bool
    {
        return in_array($this->value, self::activeValues(), true);
    }
}
