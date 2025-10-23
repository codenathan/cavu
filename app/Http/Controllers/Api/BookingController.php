<?php

namespace App\Http\Controllers\Api;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Http\Requests\CheckPriceRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Services\CapacityService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    protected PricingService $pricingService;
    protected CapacityService $capacityService;

    public function __construct(PricingService $pricingService, CapacityService $capacityService)
    {
        $this->pricingService = $pricingService;
        $this->capacityService = $capacityService;
    }


    public function checkAvailability(CheckAvailabilityRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->get('parking_from'));
        $to = Carbon::parse($request->get('parking_to'));

        $availability = $this->capacityService->getAvailability($from, $to);
        $isAvailable = $this->capacityService->isAvailable($from, $to);

        return response()->json([
            'is_available' => $isAvailable,
            'total_capacity' => CapacityService::MAX_CAPACITY,
            'daily_availability' => $availability,
            'message' => $isAvailable
                ? 'Parking space is available for the requested period.'
                : 'No parking space available for one or more days in the requested period.',
        ]);
    }

    public function checkPrice(CheckPriceRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->get('parking_from'));
        $to = Carbon::parse($request->get('parking_to'));


        $price = $this->pricingService->calculatePrice($from, $to);

        return response()->json([
            'message' => 'Parking price calculated successfully.',
            'price_gbp' => number_format($price / 100, 2), // Return price in GBP
            'price_pence' => $price,
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {

        $from = Carbon::parse($request->get('parking_from'));
        $to = Carbon::parse($request->get('parking_to'));

        // 1. Capacity Check
        if (!$this->capacityService->isAvailable($from, $to)) {
            return response()->json([
                'message' => 'Booking failed: Not enough capacity for the requested dates.',
            ], 400);
        }

        // 2. Calculate Final Price
        $pricePence = $this->pricingService->calculatePrice($from, $to);

        // 3. Create Booking
        $booking = Booking::create([
            'car_plate' => strtoupper($request->input('car_plate')),
            'customer_name' => $request->input('customer_name'),
            'parking_from' => $request->input('parking_from'),
            'parking_to' => $request->input('parking_to'),
            'price_pence' => $pricePence,
            'status' => Status::ACTIVE,
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'booking' => $booking,
        ], 201);
    }


    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        if ($booking->status === Status::CANCELLED) {
            return response()->json(['message' => 'Cannot amend a cancelled booking.'], 400);
        }

        $from = Carbon::parse($request->get('parking_from'));
        $to = Carbon::parse($request->get('parking_to'));

        $oldPrice = $booking->price;

        // 1. Capacity Check: Crucially, we ignore the current booking's ID during the check
        if (!$this->capacityService->isAvailable($from, $to, $booking->id)) {
            return response()->json(['message' => 'Amendment failed: Not enough capacity for the new dates.'], 400);
        }

        // 2. Calculate New Price
        $newPrice = $this->pricingService->calculatePrice($from, $to);

        // 3. Update Booking
        // Using a transaction ensures that all database operations succeed or fail together.
        DB::beginTransaction();
        try {
            $booking->update([
                'car_plate' => strtoupper($request->input('car_plate')),
                'customer_name' => $request->input('customer_name'),
                'parking_from' => $from,
                'parking_to' => $to,
                'price_pence' => $newPrice,
            ]);
            DB::commit();

            return response()->json([
                'message' => 'Booking amended successfully.',
                'booking' => $booking,
                'price_change_gbp' => number_format(($newPrice - $oldPrice) / 100, 2),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Amendment failed due to a server error.'], 500);
        }
    }

    public function destroy(Booking $booking): JsonResponse
    {
        if ($booking->status === Status::CANCELLED) {
            return response()->json(['message' => 'Booking is already cancelled.'], 200);
        }

        $booking->update(['status' =>  Status::CANCELLED]);

        return response()->json(['message' => 'Booking cancelled successfully.']);
    }
}
