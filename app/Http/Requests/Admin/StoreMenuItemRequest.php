<?php

namespace App\Http\Requests\Admin;

use App\Services\Ai\AiRecommendationOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:menu_items,slug'],
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4096,max_height=4096'],
            'spice_level' => ['required', 'string', 'max:50', Rule::in(AiRecommendationOptions::VALUES['spice_level'])],
            'protein_type' => ['nullable', 'string', 'max:50', Rule::in(AiRecommendationOptions::VALUES['protein_type'])],
            'dietary_type' => ['nullable', 'string', 'max:50', Rule::in(AiRecommendationOptions::VALUES['dietary_type'])],
            'flavor_profile' => ['nullable', 'string', 'max:50', Rule::in(AiRecommendationOptions::VALUES['flavor_profile'])],
            'is_available' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'spice_level' => $this->input('spice_level') ?? 'none',
            'is_available' => $this->has('is_available') ? $this->input('is_available') : false,
            'is_featured' => $this->has('is_featured') ? $this->input('is_featured') : false,
        ]);
    }

    public function messages(): array
    {
        return [
            'price.decimal' => 'The price must have no more than two decimal places.',
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
        ];
    }
}
