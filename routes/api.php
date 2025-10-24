<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\BookingController;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::prefix('v1')->group(function () {
    Route::post('parking/check-availability', [BookingController::class,'checkAvailability']);
    Route::post('parking/check-price', [BookingController::class,'checkPrice']);
    Route::apiResource('bookings', BookingController::class)->except(['index']);
});
