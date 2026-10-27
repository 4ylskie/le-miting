<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvalidateMenuAllergenAssessmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'change_type' => ['required', Rule::in(['recipe', 'ingredients', 'supplier', 'preparation'])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
