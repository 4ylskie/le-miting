<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('menu_items', 'slug')->ignore($this->route('menuItem'))],
            'menu_category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'image' => ['nullable', 'file', 'image', 'max:2048'],
            'spice_level' => ['nullable', 'string', 'max:50'],
            'protein_type' => ['nullable', 'string', 'max:50'],
            'dietary_type' => ['nullable', 'string', 'max:50'],
            'flavor_profile' => ['nullable', 'string', 'max:50'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and dashes.',
        ];
    }
}
