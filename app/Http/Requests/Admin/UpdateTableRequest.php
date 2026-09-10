<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table_number' => ['required', 'string', 'max:20', Rule::unique('tables', 'table_number')->ignore($this->route('table'))],
            'capacity' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', 'in:available,maintenance'],
        ];
    }
}
