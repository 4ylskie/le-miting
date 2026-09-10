<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Reservation;
use Illuminate\Support\Facades\URL;

class FeedbackController extends Controller
{
    public function create(string $token)
    {
        $source = $this->resolveSource($token);

        if ($source->feedback) {
            return view('customer.feedback.thank-you', [
                'transactionLabel' => $this->label($source),
            ]);
        }

        return view('customer.feedback.create', [
            'transactionLabel' => $this->label($source),
            'storeUrl' => URL::temporarySignedRoute('feedback.store', now()->addHours(24), ['token' => $token]),
        ]);
    }

    public function store(StoreFeedbackRequest $request, string $token)
    {
        $source = $this->resolveSource($token);

        if (! $source->feedback) {
            Feedback::create([
                'order_id' => $source instanceof Order ? $source->id : null,
                'reservation_id' => $source instanceof Reservation ? $source->id : null,
                'food_rating' => $request->validated('food_rating'),
                'service_rating' => $request->validated('service_rating'),
                'experience_rating' => $request->validated('experience_rating'),
                'comment' => $request->validated('comment'),
            ]);
        }

        return view('customer.feedback.thank-you', [
            'transactionLabel' => $this->label($source),
        ]);
    }

    private function resolveSource(string $token): Order|Reservation
    {
        [$type, $id] = explode('-', $token, 2);

        $source = match ($type) {
            'order' => Order::findOrFail((int) $id),
            'reservation' => Reservation::findOrFail((int) $id),
            default => abort(404),
        };

        abort_unless($source->status === 'completed', 404);

        return $source;
    }

    private function label(Order|Reservation $source): string
    {
        return $source instanceof Order
            ? 'order '.$source->order_number
            : 'reservation '.$source->reservation_number;
    }
}
