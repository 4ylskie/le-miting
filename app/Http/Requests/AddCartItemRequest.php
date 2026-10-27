<?php

namespace App\Http\Requests;

use App\Models\MenuCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where('is_available', true)->whereNull('deleted_at')
                    ->where(fn ($query) => $query->whereIn('menu_category_id', MenuCategory::where('is_active', true)->select('id'))),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_item_id.exists' => 'This menu item is no longer available.',
        ];
    }
}
