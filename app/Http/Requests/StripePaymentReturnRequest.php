<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StripePaymentReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return config('payment.driver') === 'stripe';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
            'session_id' => ['required_unless:cancelled,1', 'nullable', 'string', 'max:255', 'regex:/\Acs_test_[A-Za-z0-9_]+\z/'],
            'cancelled' => ['sometimes', 'boolean'],
        ];
    }
}
