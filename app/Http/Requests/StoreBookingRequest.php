<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parking_from' => 'required|date|after_or_equal:today',
            'parking_to' => 'required|date|after_or_equal:parking_from',
            'car_plate' => 'required|string|max:10',
            'customer_name' => 'required|string|max:100',
        ];
    }
}
