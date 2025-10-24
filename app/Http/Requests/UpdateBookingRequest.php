<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
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
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_email' => 'sometimes|nullable|email|max:255',
            'car_plate' => 'sometimes|nullable|string|max:50',
            'parking_from' => 'sometimes|date',
            'parking_to' => 'sometimes|date|after_or_equal:parking_from',
        ];
    }
}
