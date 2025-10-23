<?php

namespace Tests\Unit;

use App\Services\CapacityService;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

class CapacityServiceTest extends TestCase
{
    protected CapacityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Mockery::mock(CapacityService::class)
            ->makePartial();
    }

    public function test_returns_true_when_spaces_are_available()
    {
        $from = Carbon::create(2025, 10, 23);
        $to = Carbon::create(2025, 10, 25);

        // Mock getOccupiedSpaces to always return less than capacity
        $this->service->shouldReceive('getOccupiedSpaces')
            ->andReturn(5);

        $result = $this->service->isAvailable($from, $to);

        $this->assertTrue($result);
    }

    public function test_returns_false_when_parking_is_full()
    {
        $from = Carbon::create(2025, 10, 23);
        $to = Carbon::create(2025, 10, 24);

        // One of the days returns 10 (full)
        $this->service->shouldReceive('getOccupiedSpaces')
            ->andReturn(10);

        $result = $this->service->isAvailable($from, $to);

        $this->assertFalse($result);
    }

    public function test_returns_availability_array_for_each_day()
    {
        $from = Carbon::create(2025, 10, 23);
        $to = Carbon::create(2025, 10, 25);

        // Mock different values for each day
        $this->service->shouldReceive('getOccupiedSpaces')
            ->andReturn(3, 10, 5); // day1:3, day2:10, day3:5

        $availability = $this->service->getAvailability($from, $to);

        $this->assertCount(3, $availability);

        // Check first day
        $this->assertEquals('2025-10-23', $availability[0]['date']);
        $this->assertEquals(7, $availability[0]['available_spaces']);
        $this->assertTrue($availability[0]['is_available']);

        // Check second day (fully booked)
        $this->assertEquals(0, $availability[1]['available_spaces']);
        $this->assertFalse($availability[1]['is_available']);
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }



}
