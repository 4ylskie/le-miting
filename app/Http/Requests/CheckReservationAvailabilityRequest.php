<?php

namespace App\Http\Requests;

use App\Services\BusinessTime;
use Illuminate\Foundation\Http\FormRequest;

class CheckReservationAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(BusinessTime $businessTime): array
    {
        return [
            'reservation_type' => ['required', 'in:table_only,table_and_food'],
            'reservation_date' => ['required', 'date', 'after_or_equal:'.$businessTime->today()],
            'reservation_time' => ['required', 'date_format:H:i'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
