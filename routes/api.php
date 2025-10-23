<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BookingController;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::post('check-availability', [BookingController::class,'checkAvailability']);
Route::post('check-price', [BookingController::class,'checkPrice']);
Route::apiResource('bookings', BookingController::class)->except(['index']);

Route::apiResource('tasks', BookingController::class);
