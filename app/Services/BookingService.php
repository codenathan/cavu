<?php

namespace App\Services;

use App\Enums\Status;
use App\Exceptions\BookingAmendmentNotAllowedException;
use App\Exceptions\CapacityExceededException;
use App\Interfaces\BookingRepositoryInterface;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected PricingService $pricingService,
        protected CapacityService $capacityService,
        protected BookingRepositoryInterface $bookingRepository
    ) {
    }

    /**
     * Checks parking availability for the given period.
     */
    public function checkAvailability(Carbon $from, Carbon $to): array
    {
        $availability = $this->capacityService->getAvailability($from, $to);
        $isAvailable = $this->capacityService->isAvailable($from, $to);

        return [
            'is_available' => $isAvailable,
            'total_capacity' => CapacityService::MAX_CAPACITY,
            'daily_availability' => $availability,
        ];
    }

    /**
     * Calculates the parking price for the given period in pence.
     */
    public function calculatePrice(Carbon $from, Carbon $to): int
    {
        return $this->pricingService->calculatePrice($from, $to);
    }

    /**
     * Creates a new booking after checking capacity and calculating the price.
     *
     * @throws CapacityExceededException
     */
    public function createBooking(array $data): Booking
    {
        $from = Carbon::parse($data['parking_from']);
        $to = Carbon::parse($data['parking_to']);

        // 1. Capacity Check
        if (!$this->capacityService->isAvailable($from, $to)) {
            throw new CapacityExceededException('Not enough capacity for the requested dates.');
        }

        // 2. Calculate Final Price
        $pricePence = $this->pricingService->calculatePrice($from, $to);

        // 3. Prepare data for Repository
        $bookingData = [
            'car_plate' => strtoupper($data['car_plate']),
            'customer_name' => $data['customer_name'],
            'parking_from' => $from,
            'parking_to' => $to,
            'price' => $pricePence,
            'status' => Status::ACTIVE,
        ];

        // 4. Create Booking via Repository
        return $this->bookingRepository->create($bookingData);
    }

    /**
     * Updates an existing booking after checking capacity and calculating the new price.
     *
     * @throws CapacityExceededException
     * @throws BookingAmendmentNotAllowedException
     * @throws \RuntimeException
     */
    public function amendBooking(Booking $booking, array $data): array
    {

        if ($booking->status === Status::CANCELLED) {
            throw new BookingAmendmentNotAllowedException('Cannot amend a cancelled booking.');
        }

        $from = Carbon::parse($data['parking_from']);
        $to = Carbon::parse($data['parking_to']);
        $oldPrice = $booking->price;

        // 1. Capacity Check: Exclude the current booking's ID during the check
        if (!$this->capacityService->isAvailable($from, $to, $booking->id)) {
            throw new CapacityExceededException('Amendment failed: Not enough capacity for the new dates.');
        }

        // 2. Calculate New Price
        $newPrice = $this->pricingService->calculatePrice($from, $to);

        // 3. Update Booking via Transaction
        DB::beginTransaction();
        try {
            $updateData = [
                'car_plate' => isset($data['car_plate']) ? strtoupper($data['car_plate']) : $booking->car_plate,
                'customer_name' => $data['customer_name'] ?? $booking->customer_name,
                'parking_from' => $from ?? $booking->parking_from,
                'parking_to' => $to ?? $booking->parking_to,
                'price' => $newPrice,
            ];

            $this->bookingRepository->update($booking, $updateData);
            DB::commit();


            $booking->refresh();
            return [
                'booking' => $booking,
                'price_change_pence' => $newPrice - $oldPrice,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw new \RuntimeException('Amendment failed due to a database error.', 0, $e);
        }
    }

    public function cancelBooking(Booking $booking): bool
    {
        if ($booking->status === Status::CANCELLED) {
            return true;
        }

        return $this->bookingRepository->softDelete($booking);
    }


    public function getMaxBookingCapacity(): int
    {
        return CapacityService::MAX_CAPACITY;
    }
}
