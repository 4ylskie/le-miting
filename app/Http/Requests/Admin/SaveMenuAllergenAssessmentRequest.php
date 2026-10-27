<?php

namespace App\Http\Requests\Admin;

use App\Models\MenuItemAllergenAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveMenuAllergenAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'recipe_status' => ['required', Rule::in(array_keys(MenuItemAllergenAssessment::RECIPE))],
            'cross_contact_status' => ['required', Rule::in(array_keys(MenuItemAllergenAssessment::CROSS_CONTACT))],
            'evidence' => ['required', 'string', 'max:2000'],
            'documented_information' => ['accepted'],
            'context' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'reviewed_by' => ['prohibited'], 'reviewed_at' => ['prohibited'],
            'menu_fingerprint' => ['prohibited'], 'invalidated_at' => ['prohibited'],
        ];
    }
}
