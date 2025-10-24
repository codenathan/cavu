<?php

namespace App\Providers;

use App\Interfaces\BookingRepositoryInterface;
use App\Repositories\EloquentBookingRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            BookingRepositoryInterface::class,
            EloquentBookingRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
