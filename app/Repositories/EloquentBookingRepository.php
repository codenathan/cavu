<?php

namespace App\Repositories;

use App\Interfaces\BookingRepositoryInterface;
use App\Models\Booking;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Collection;

/**
 * Concrete implementation of the BookingRepositoryInterface using Eloquent.
 */
class EloquentBookingRepository implements BookingRepositoryInterface
{
    /**
     * Retrieve a booking by its ID.
     */
    public function findById(int $bookingId): ?Booking
    {
        return Booking::find($bookingId);
    }

    /**
     * Create a new booking record.
     */
    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    /**
     * Update an existing booking record.
     */
    public function update(Booking $booking, array $data): bool
    {
        return $booking->update($data);
    }

    /**
     * Delete a booking record by setting its status to CANCELLED.
     */
    public function softDelete(Booking $booking): bool
    {
        return $booking->update(['status' => Status::CANCELLED]);
    }

    /**
     * Get all active bookings within a date range (for capacity checks or reporting).
     */
    public function getActiveBookings(string $from, string $to): Collection
    {
        return Booking::where('status', Status::ACTIVE)
            ->where('parking_from', '<=', $to)
            ->where('parking_to', '>=', $from)
            ->get();
    }
}
