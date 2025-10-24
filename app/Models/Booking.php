<?php

namespace App\Models;

use App\Enums\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static active()
 */
class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;


    protected $casts = [
        'status' => Status::class,
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $fillable = [
      'parking_from',
      'parking_to',
      'car_plate',
      'customer_name',
      'status',
        'price'
    ];


    public function scopeActive($query)
    {
        return $query->where('status', Status::ACTIVE);
    }
}
