<?php
namespace App\Services;

use Carbon\Carbon;

class PricingService
{

    public const WEEKDAY_PRICE = 1500;
    public const WEEKEND_PRICE = 2000;

    public const SUMMER_START_MONTH = 6;
    public const SUMMER_END_MONTH = 9;
    public const WINTER_START_MONTH = 11;
    public const WINTER_END_MONTH = 12;
    public const WINTER_SUMMER_SURCHARGE = 500;

    /**
     * Calculate the total parking price for a given date range by iterating through each day.
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int Total price in pence
     */
    public function calculatePrice(Carbon $startDate, Carbon $endDate) : int
    {
        $totalDays = $startDate->diffInDays($endDate);

        // minimum 1-day booking in case it's less than 24 hours
        $totalDays = $totalDays > 0 ? $totalDays : 1;

        $totalPrice = 0;
        $currentDate = $startDate->copy()->startOfDay();

        for ($i = 0; $i < $totalDays; $i++) {
            $dailyRate = self::WEEKDAY_PRICE;

            // 1. Weekend Pricing
            if ($currentDate->isWeekend()) {
                $dailyRate = self::WEEKEND_PRICE;
            }

            // 2. Summer or Winter Surcharge
            $isSummer = $currentDate->month >= self::SUMMER_START_MONTH
                && $currentDate->month <= self::SUMMER_END_MONTH;
            $isWinter = $currentDate->month >= self::WINTER_START_MONTH
                && $currentDate->month <= self::WINTER_END_MONTH;
            if ($isSummer || $isWinter) {
                $dailyRate += self::WINTER_SUMMER_SURCHARGE;
            }

            $totalPrice += $dailyRate;
            $currentDate->addDay();
        }

        return $totalPrice;
    }
}
