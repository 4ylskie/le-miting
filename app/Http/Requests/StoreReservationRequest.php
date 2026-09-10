<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservation_type' => ['required', 'in:table_only,table_and_food'],
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'special_request' => ['nullable', 'string', 'max:1000'],
            'food_items' => ['required_if:reservation_type,table_and_food', 'array'],
            'food_items.*.menu_item_id' => ['required_if:reservation_type,table_and_food', 'integer', Rule::exists('menu_items', 'id')->where('is_available', true)],
            'food_items.*.quantity' => ['required_if:reservation_type,table_and_food', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'table_id.required' => 'Please select an available table.',
            'guest_count.max' => 'Please choose a table that can seat your group.',
            'food_items.*.menu_item_id.exists' => 'One of the selected dishes is no longer available.',
        ];
    }
}
