<?php

namespace App\Http\Requests;

use App\Models\Allergen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecommendMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences_text' => ['nullable', 'string', 'max:300'],
            'excluded_allergens' => ['nullable', 'array'],
            'excluded_allergens.*' => ['required', 'string', 'distinct:strict', Rule::in(Allergen::CODES)],
            'category' => ['prohibited'],
            'spice_level' => ['prohibited'],
            'protein' => ['prohibited'],
            'protein_type' => ['prohibited'],
            'dietary' => ['prohibited'],
            'dietary_type' => ['prohibited'],
            'flavor' => ['prohibited'],
            'flavor_profile' => ['prohibited'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $text = $this->input('preferences_text');
            $selectedAllergens = $this->input('excluded_allergens');

            if ((is_string($text) && trim($text) !== '')
                || (is_array($selectedAllergens) && $selectedAllergens !== [])) {
                return;
            }

            $validator->errors()->add('preferences_text', 'Enter a preference or select at least one allergen to avoid.');
        }];
    }
}
