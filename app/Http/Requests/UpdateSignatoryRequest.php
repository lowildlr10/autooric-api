<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSignatoryRequest extends FormRequest
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
            'signatory_name' => 'required',
            'report_module' => 'nullable',
            'is_active' => 'boolean|required'
        ];
    }
}
