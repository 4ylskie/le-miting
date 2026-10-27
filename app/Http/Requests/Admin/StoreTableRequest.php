<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_number' => ['required', 'string', 'max:20', 'unique:tables,table_number'],
            'capacity' => ['required', 'integer', Rule::in(array_keys(config('reservation.capacity_rules')))],
            'status' => ['required', 'in:available,maintenance'],
        ];
    }
}
