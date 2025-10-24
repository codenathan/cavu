<?php

namespace Database\Factories;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->name(),
            'car_plate' => fake()->regexify('[A-Z]{3}[0-9]{3}'),
            'parking_from' => fake()->dateTimeBetween('-1 week', '+1 week')->format('Y-m-d'),
            'parking_to' => fake()->dateTimeBetween('+1 week', '+2 week')->format('Y-m-d'),
            'price' => fake()->numberBetween(1000, 5000),
            'status' => Status::ACTIVE
        ];
    }
}
