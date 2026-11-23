<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class AppTime
{
    /**
     * Parse a client timestamp ("2026-11-24T03:00:00.000Z", "2026-11-24 10:00", ...) and
     * convert it to the application timezone. Booking columns are `timestamp without time
     * zone`, so every value must be in one agreed zone before it reaches the database.
     */
    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }
}
