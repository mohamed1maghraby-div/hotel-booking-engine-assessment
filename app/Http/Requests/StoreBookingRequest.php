<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return false;
        return $this->user() !== null;
    }


    protected function prepareForValidation(): void
    {
        $this->merge([
            'hotel_id' => $this->input('hotel_id'),
            'room_type_id' => $this->input('room_type_id'),
            'check_in' => $this->input('check_in'),
            'check_out' => $this->input('check_out'),
            'quantity' => (int) $this->input('quantity'),
        ]);
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hotel_id' => [
                'required',
                'integer',
                'min:1',
                'exists:hotels,id'
            ],
            'room_type_id' => [
                'required',
                'integer',
                'min:1',
                'exists:room_types,id'
            ],
            'check_in' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today'
            ],
            'check_out' => [
                'required',
                'date_format:Y-m-d',
                'after:check_in'
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:10'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'check_in.required' => 'Check-in date is required.',
            'check_in.date_format' => 'Check-in date must be in the format YYYY-MM-DD.',
            'check_in.after_or_equal' => 'Check-in date cannot be in the past.',

            'check_out.required' => 'Check-out date is required.',
            'check_out.date_format' => 'Check-out date must be in the format YYYY-MM-DD.',
            'check_out.after' => 'Check-out date must be after the check-in date.',

            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be an integer.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 10.'
        ];
    }
}
