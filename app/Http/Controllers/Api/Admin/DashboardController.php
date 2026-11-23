<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        return response()->json([
            'pending_count' => Booking::where('status', BookingStatus::Pending)->where('end_time', '>', now())->count(),
            'today_count' => Booking::active()->overlapping($todayStart, $todayEnd)->count(),
            'active_rooms' => Room::active()->count(),
            'users' => User::count(),
            'by_status' => Booking::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }
}
