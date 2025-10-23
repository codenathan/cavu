<?php

namespace Tests\Unit;

use App\Services\PricingService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    protected PricingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PricingService();
    }

    public function test_weekday_pricing()
    {
        $start = Carbon::create(2025, 2, 17); // Monday
        $end = Carbon::create(2025, 2, 18);   // Tuesday

        $price = $this->service->calculatePrice($start, $end);

        // 1 day * weekday price (1500)
        $this->assertEquals(1500, $price);
    }

    public function test_weekend_pricing()
    {
        $start = Carbon::create(2025, 2, 15); // Saturday
        $end = Carbon::create(2025, 2, 16);   // Sunday

        $price = $this->service->calculatePrice($start, $end);

        // 1 day * weekend price (2000)
        $this->assertEquals(2000, $price);
    }

    public function test_summer_surcharge()
    {
        $start = Carbon::create(2025, 7, 1); // July (summer)
        $end = Carbon::create(2025, 7, 2);   // 1 day later

        $price = $this->service->calculatePrice($start, $end);

        // Weekday (1500) + Summer surcharge (500) = 2000
        $this->assertEquals(2000, $price);
    }

    public function test_winter_surcharge()
    {
        $start = Carbon::create(2025, 12, 10); // December (winter)
        $end = Carbon::create(2025, 12, 11);

        $price = $this->service->calculatePrice($start, $end);

        // Weekday (1500) + Winter surcharge (500) = 2000
        $this->assertEquals(2000, $price);
    }

    public function test_weekend_and_summer_surcharge()
    {
        $start = Carbon::create(2025, 6, 7); // Saturday in June
        $end = Carbon::create(2025, 6, 8);   // Sunday (1 day diff)

        $price = $this->service->calculatePrice($start, $end);

        // Weekend (2000) + Summer surcharge (500) = 2500
        $this->assertEquals(2500, $price);
    }

    public function test_multiple_days_correctly()
    {
        // 3 days: Fri (weekday), Sat (weekend), Sun (weekend)
        $start = Carbon::create(2025, 5, 16); // Friday
        $end = Carbon::create(2025, 5, 19);   // Monday (3 days diff)

        $price = $this->service->calculatePrice($start, $end);

        // Fri: 1500 + Sat: 2000 + Sun: 2000 = 5500
        $this->assertEquals(5500, $price);
    }

    public function test_same_day_pricing()
    {
        $date = Carbon::create(2025, 5, 16); // same day
        $price = $this->service->calculatePrice($date, $date);

        // Minimum 1-day charge (weekday)
        $this->assertEquals(1500, $price);
    }


}
