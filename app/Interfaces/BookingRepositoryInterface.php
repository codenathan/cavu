<?php

namespace App\Interfaces;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

interface BookingRepositoryInterface
{
    /**
     * Retrieve a booking by its ID.
     */
    public function findById(int $bookingId): ?Booking;

    /**
     * Create a new booking record.
     */
    public function create(array $data): Booking;

    /**
     * Update an existing booking record.
     */
    public function update(Booking $booking, array $data): bool;

    /**
     * Delete a booking record by setting its status to CANCELLED (soft delete).
     */
    public function softDelete(Booking $booking): bool;
}
