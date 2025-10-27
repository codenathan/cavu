<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\CapacityService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private array $baseData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseData = [
            'parking_from' => Carbon::now()->addDays(2)->toDateString(),
            'parking_to' => Carbon::now()->addDays(5)->toDateString(),
            'car_plate' => 'CAVU123',
            'customer_name' => 'John Doe',
        ];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function test_shows_full_capacity_when_no_bookings_exist()
    {
        $response = $this->getJson('api/v1/parking/check-availability?' . http_build_query($this->baseData));

        $response->assertStatus(200)
            ->assertJson([
                'is_available' => true,
                'total_capacity' => CapacityService::MAX_CAPACITY,
                'message' => 'Parking space is available for the requested period.'
            ]);

        $this->assertEquals(
            CapacityService::MAX_CAPACITY,
            $response['daily_availability'][0]['available_spaces']
        );
    }

    public function test_booking_is_blocked_when_capacity_is_fully_booked()
    {

        for ($i = 0; $i < CapacityService::MAX_CAPACITY; $i++) {
            Booking::factory()->create(array_merge($this->baseData, ['car_plate' => 'FULL' . $i]));
        }

        $response = $this->postJson('api/v1/bookings', $this->baseData);
        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Booking failed: Not enough capacity for the requested dates.']);
    }

    public function test_customer_can_amend_a_booking_if_new_dates_are_available()
    {
        // Initial booking
        $booking = Booking::factory()->create(
            array_merge($this->baseData, ['price' => 3000])
        );

        $newDates = [
            'parking_from' => Carbon::now()->addDays(7)->toDateString(),
            'parking_to' => Carbon::now()->addDays(7)->toDateString(),
        ];
        $updateData = array_merge($newDates, ['car_plate' => 'NEWPLATE']);

        $response = $this->putJson("api/v1/bookings/{$booking->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Booking amended successfully.']);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'car_plate' => 'NEWPLATE',
            'price' => 2000,
        ]);

        $response->assertJsonFragment(['price_change_gbp' => '-10.00']);
    }

    public function test_an_amendment_fails_if_new_dates_have_no_capacity()
    {
        // 1. Fill all spaces for a future period (Mon-Tue)
        $fullPeriod = [
            'parking_from' => Carbon::now()->addDays(7)->toDateString(),
            'parking_to' => Carbon::now()->addDays(8)->toDateString(),
        ];
        for ($i = 0; $i < CapacityService::MAX_CAPACITY; $i++) {
            Booking::factory()->create(array_merge($fullPeriod, ['car_plate' => 'FULL' . $i]));
        }

        // 2. Create a booking in a non-full period
        $booking = Booking::factory()->create($this->baseData);

        // 3. Attempt to amend the booking to the full period (Mon-Tue)
        $updateData = array_merge($fullPeriod, ['car_plate' => 'TESTING']);
        $response = $this->putJson("api/v1/bookings/{$booking->id}", $updateData);

        // Assert failure due to capacity
        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Amendment failed: Not enough capacity for the new dates.']);

        // Assert the original booking remains unchanged
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'active', 'car_plate' => 'CAVU123']);
    }

    public function test_cancelling_a_booking_changes_status_and_frees_up_capacity()
    {
        // 1. Create 10 bookings to fill capacity
        for ($i = 0; $i < CapacityService::MAX_CAPACITY; $i++) {
            Booking::factory()->create(array_merge($this->baseData, ['car_plate' => 'FULL' . $i]));
        }
        $bookingToCancel = Booking::latest()->first();

        // 2. Check for zero availability
        $checkBefore = $this->getJson('api/v1/parking/check-availability?'.http_build_query($this->baseData));

        $this->assertFalse($checkBefore['is_available']);

        // 3. Cancel the booking
        $response = $this->deleteJson("api/v1/bookings/{$bookingToCancel->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Booking cancelled successfully.']);
        $this->assertDatabaseHas('bookings', ['id' => $bookingToCancel->id, 'status' => 'cancelled']);

        // 4. Check for increased availability (should be 1 space free now)
        $checkAfter = $this->getJson('api/v1/parking/check-availability?'. http_build_query($this->baseData));
        $this->assertTrue($checkAfter['is_available']);
    }
}
