<?php

namespace Tests\Unit;

use App\Enums\Status;
use App\Exceptions\BookingAmendmentNotAllowedException;
use App\Exceptions\CapacityExceededException;
use App\Interfaces\BookingRepositoryInterface;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CapacityService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    // Use this to ensure Carbon instances are predictable
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2025-10-25 10:00:00');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_create_booking_success()
    {
        $mockPricingService = Mockery::mock(PricingService::class);
        $mockCapacityService = Mockery::mock(CapacityService::class);
        $mockBookingRepository = Mockery::mock(BookingRepositoryInterface::class);

        // Define expected behaviors
        $mockPricingService->shouldReceive('calculatePrice')->andReturn(2500)->once();
        $mockCapacityService->shouldReceive('isAvailable')->andReturn(true)->once();

        // Expect the repository to be called with the fully calculated data
        $expectedBookingData = [
            'car_plate' => 'ABC123Z',
            'customer_name' => 'John Doe',
            'parking_from' => Carbon::parse('2025-11-01'),
            'parking_to' => Carbon::parse('2025-11-05'),
            'price' => 2500,
            'status' => Status::ACTIVE,
        ];

        // Mock the repository creation call
        $mockBookingRepository->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($expectedBookingData) {
                return $data['price'] === $expectedBookingData['price']
                    && $data['status'] === $expectedBookingData['status']
                    && $data['car_plate'] === $expectedBookingData['car_plate'];
            })
            ->andReturn(new Booking($expectedBookingData));

        // Instantiate the service with mocked dependencies
        $bookingService = new BookingService(
            $mockPricingService,
            $mockCapacityService,
            $mockBookingRepository
        );

        $bookingData = [
            'car_plate' => 'abc123z',
            'customer_name' => 'John Doe',
            'parking_from' => '2025-11-01',
            'parking_to' => '2025-11-05',
        ];

        $booking = $bookingService->createBooking($bookingData);

        $this->assertInstanceOf(Booking::class, $booking);
        $this->assertEquals(2500, $booking->price);
        $this->assertEquals(Status::ACTIVE, $booking->status);
    }

    public function test_create_booking_fails_on_capacity_exceeded()
    {
        $mockPricingService = Mockery::mock(PricingService::class);
        $mockCapacityService = Mockery::mock(CapacityService::class);
        $mockBookingRepository = Mockery::mock(BookingRepositoryInterface::class);

        // Capacity check fails
        $mockCapacityService->shouldReceive('isAvailable')->andReturn(false)->once();
        // Price calculation and repository creation should never be called
        $mockPricingService->shouldNotReceive('calculatePrice');
        $mockBookingRepository->shouldNotReceive('create');

        $bookingService = new BookingService(
            $mockPricingService,
            $mockCapacityService,
            $mockBookingRepository
        );

        $bookingData = [
            'parking_from' => '2025-11-01',
            'parking_to' => '2025-11-05',
            'car_plate' => 'ABC',
            'customer_name' => 'Jane Doe',
        ];

        $this->expectException(CapacityExceededException::class);

        $bookingService->createBooking($bookingData);
    }

    public function test_amend_booking_success()
    {
        // Mock DB transactions
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();
        DB::shouldReceive('rollBack')->never();

        // Mock services and repository
        $mockPricingService = Mockery::mock(PricingService::class);
        $mockCapacityService = Mockery::mock(CapacityService::class);
        $mockBookingRepository = Mockery::mock(BookingRepositoryInterface::class);

        // Capacity is available
        $mockCapacityService->shouldReceive('isAvailable')->once()->andReturn(true);

        // Pricing calculation returns 3000
        $mockPricingService->shouldReceive('calculatePrice')->once()->andReturn(3000);

        // Create a real booking object
        $booking = new Booking([
            'id' => 5,
            'price' => 2000, // old price
            'status' => Status::ACTIVE,
            'car_plate' => 'OLDPLATE',
        ]);

        // Mock repository update to actually update the booking
        $mockBookingRepository->shouldReceive('update')
            ->once()
            ->with($booking, Mockery::on(function ($data) {
                return $data['price'] === 3000 && $data['car_plate'] === 'NEWPLATE';
            }))
            ->andReturnUsing(function ($booking, $data) {
                $booking->price = $data['price'];
                $booking->car_plate = $data['car_plate'];
                return true;
            });

        // Instantiate service
        $bookingService = new BookingService(
            $mockPricingService,
            $mockCapacityService,
            $mockBookingRepository
        );

        // Amendment data
        $amendmentData = [
            'car_plate' => 'NEWPLATE',
            'customer_name' => 'New Name',
            'parking_from' => '2025-12-01',
            'parking_to' => '2025-12-10',
        ];

        $result = $bookingService->amendBooking($booking, $amendmentData);

        $this->assertEquals(3000, $result['booking']->price);
        $this->assertEquals(1000, $result['price_change_pence']);
    }

    public function test_amend_booking_fails_on_cancelled_status()
     {
         DB::shouldReceive('beginTransaction')->never();
         DB::shouldReceive('commit')->never();
         DB::shouldReceive('rollBack')->never();

         $mockPricingService = Mockery::mock(PricingService::class);
         $mockCapacityService = Mockery::mock(CapacityService::class);
         $mockBookingRepository = Mockery::mock(BookingRepositoryInterface::class);

         $booking = new Booking([
             'status' => Status::CANCELLED,
             'price' => 1000,
         ]);

         $bookingService = new BookingService(
             $mockPricingService,
             $mockCapacityService,
             $mockBookingRepository
         );

         // Assert that none of the core logic functions are called
         $mockCapacityService->shouldNotReceive('isAvailable');
         $mockPricingService->shouldNotReceive('calculatePrice');
         $mockBookingRepository->shouldNotReceive('update');



         $this->expectException(BookingAmendmentNotAllowedException::class);

         $bookingService->amendBooking($booking, []);
     }

     public function test_cancel_booking_success()
         {
             $mockPricingService = Mockery::mock(PricingService::class);
             $mockCapacityService = Mockery::mock(CapacityService::class);
             $mockBookingRepository = Mockery::mock(BookingRepositoryInterface::class);

             $booking = new Booking(['status' => Status::ACTIVE]);

             // Expect the repository to be called to soft delete
             $mockBookingRepository->shouldReceive('softDelete')->once()->with($booking)->andReturn(true);

             $bookingService = new BookingService(
                 $mockPricingService,
                 $mockCapacityService,
                 $mockBookingRepository
             );

             $result = $bookingService->cancelBooking($booking);

             $this->assertTrue($result);
         }
}
