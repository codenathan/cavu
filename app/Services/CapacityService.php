<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class CapacityService
{
    public const MAX_CAPACITY = 10;


    /**
     * Check if space is available for the given period
     */
    public function isAvailable(Carbon $from, Carbon $to, ?int $excludeBookingId = null): bool
    {
        $period = CarbonPeriod::create($from->startOfDay(), $to->startOfDay());

        foreach ($period as $date) {
            $occupiedSpaces = $this->getOccupiedSpaces($date, $excludeBookingId);
            if ($occupiedSpaces >= self::MAX_CAPACITY) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available spaces for each day in the given period
     */
    public function getAvailability(Carbon $from, Carbon $to): array
    {
        $period = CarbonPeriod::create($from->startOfDay(), $to->startOfDay());
        $availability = [];

        foreach ($period as $date) {
            $occupied = $this->getOccupiedSpaces($date);
            $available = self::MAX_CAPACITY - $occupied;

            $availability[] = [
                'date' => $date->format('Y-m-d'),
                'day_of_week' => $date->format('l'),
                'total_spaces' => self::MAX_CAPACITY,
                'occupied_spaces' => $occupied,
                'available_spaces' => $available,
                'is_available' => $available > 0,
            ];
        }

        return $availability;
    }

    /**
     * Get a number of occupied spaces for a specific date
     */
    public function getOccupiedSpaces(Carbon $date, ?int $excludeBookingId = null): int
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        return Booking::active()
            ->where(function ($query) use ($startOfDay, $endOfDay) {
                $query->where(function ($q) use ($startOfDay, $endOfDay) {
                    // Booking starts on this day
                    $q->whereBetween('parking_from', [$startOfDay, $endOfDay]);
                })->orWhere(function ($q) use ($startOfDay, $endOfDay) {
                    // Booking ends on this day
                    $q->whereBetween('parking_to', [$startOfDay, $endOfDay]);
                })->orWhere(function ($q) use ($startOfDay, $endOfDay) {
                    // Booking spans across this day
                    $q->where('parking_from', '<=', $startOfDay)
                        ->where('parking_to', '>=', $endOfDay);
                });
            })
            ->when($excludeBookingId, fn($q) => $q->where('id', '!=', $excludeBookingId))
            ->count();
    }

}
