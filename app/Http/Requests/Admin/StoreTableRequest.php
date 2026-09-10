<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', 'in:available,maintenance'],
        ];
    }
}
