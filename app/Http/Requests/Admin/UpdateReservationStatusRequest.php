<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,confirmed,completed,cancelled'],
            'expected_status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'expected_status.required' => 'Refresh the page before updating this reservation; its viewed status is missing.',
            'expected_status.in' => 'Refresh the page before updating this reservation; its viewed status is invalid.',
        ];
    }
}
