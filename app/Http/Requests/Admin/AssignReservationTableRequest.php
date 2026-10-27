<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignReservationTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', Rule::exists('tables', 'id')],
            'expected_table_id' => ['required', 'integer', Rule::exists('tables', 'id')],
            'expected_status' => ['required', 'in:pending,confirmed,completed,cancelled'],
        ];
    }
}
