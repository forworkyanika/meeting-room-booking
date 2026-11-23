<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BookingStatusTest extends TestCase
{
    #[Test]
    public function only_pending_and_approved_bookings_hold_a_slot(): void
    {
        $this->assertSame(['pending', 'approved'], BookingStatus::activeValues());

        $this->assertTrue(BookingStatus::Pending->isActive());
        $this->assertTrue(BookingStatus::Approved->isActive());
        $this->assertFalse(BookingStatus::Rejected->isActive());
        $this->assertFalse(BookingStatus::Cancelled->isActive());
    }
}
