<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfficialReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user is user role is admin or staff
        return $this->user()->role === 'admin' || $this->user()->role === 'staff';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Validate the request
            'receipt_date' => 'required|date',
            'or_no' => 'required',
            'payor_id' => 'required',
            'nature_collection_id' => 'required',
            'amount' => 'required',
            'discount_id' => 'nullable',
            'deposit' => 'nullable',
            'amount_words' => 'required',
            'card_no' => 'nullable',
            'payment_mode' => 'required'
        ];
    }

    // Return json response if validation fails
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json($validator->errors(), 422));
    }
}
