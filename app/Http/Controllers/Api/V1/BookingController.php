<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Http\Requests\CheckPriceRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Exceptions\CapacityExceededException;
use App\Exceptions\BookingAmendmentNotAllowedException;

class BookingController extends Controller
{


    public function __construct(private readonly BookingService $bookingService)
    {}

    public function checkAvailability(CheckAvailabilityRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->get('parking_from'));
        $to = Carbon::parse($request->get('parking_to'));

        $result = $this->bookingService->checkAvailability($from, $to);

        return response()->json([
            'is_available' => $result['is_available'],
            'total_capacity' => $this->bookingService->getMaxBookingCapacity(),
            'daily_availability' => $result['daily_availability'],
            'message' => $result['is_available']
                ? 'Parking space is available for the requested period.'
                : 'No parking space available for one or more days in the requested period.',
        ]);
    }

    public function checkPrice(CheckPriceRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->get('parking_from'));
        $to = Carbon::parse($request->get('parking_to'));

        $price = $this->bookingService->calculatePrice($from, $to);

        return response()->json([
            'message' => 'Parking price calculated successfully.',
            'price_gbp' => number_format($price / 100, 2), // Return price in GBP
            'price_pence' => $price,
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            // The service handles capacity check, price calculation, and persistence
            $booking = $this->bookingService->createBooking($request->validated());

            return response()->json([
                'message' => 'Booking created successfully.',
                'booking' => $booking,
            ], 201);
        } catch (CapacityExceededException $e) {
            // Controller handles the HTTP response status (400) based on the business exception
            return response()->json([
                'message' => 'Booking failed: ' . $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            // General exception handler for unexpected errors
            // In a real app, you would log $e->getMessage()
            return response()->json(['message' => 'Booking creation failed due to a server error.'], 500);
        }
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        try {
            $result = $this->bookingService->amendBooking($booking, $request->validated());

            return response()->json([
                'message' => 'Booking amended successfully.',
                'booking' => $result['booking'],
                'price_change_gbp' => number_format($result['price_change_pence'] / 100, 2),
            ]);
        } catch (BookingAmendmentNotAllowedException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (CapacityExceededException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Amendment failed due to a server error.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancels a booking.
     * Delegates business logic to BookingService.
     */
    public function destroy(Booking $booking): JsonResponse
    {
        $this->bookingService->cancelBooking($booking);

        return response()->json(['message' => 'Booking cancelled successfully.']);
    }

}
