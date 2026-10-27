<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,confirmed,preparing,ready,completed,cancelled'],
            'expected_status' => ['required', 'in:pending,confirmed,preparing,ready,completed,cancelled'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'expected_status.required' => 'Refresh the page before updating this order; its viewed status is missing.',
            'expected_status.in' => 'Refresh the page before updating this order; its viewed status is invalid.',
        ];
    }
}
