<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $dimensions = ['food_rating', 'service_rating', 'experience_rating'];

        $dimension = $request->filled('dimension') && in_array($request->string('dimension')->toString(), $dimensions, true)
            ? $request->string('dimension')->toString()
            : null;

        $rating = $request->filled('rating') ? $request->integer('rating') : null;

        $items = Feedback::with(['order', 'reservation'])
            ->when($dimension && $rating, fn ($query) => $query->where($dimension, $rating))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $averages = [
            'food' => (float) Feedback::avg('food_rating'),
            'service' => (float) Feedback::avg('service_rating'),
            'experience' => (float) Feedback::avg('experience_rating'),
        ];

        return view('admin.feedback.index', [
            'items' => $items,
            'dimensions' => $dimensions,
            'dimension' => $dimension,
            'rating' => $rating,
            'averages' => $averages,
        ]);
    }

    public function show(Feedback $feedback)
    {
        return view('admin.feedback.show', [
            'feedback' => $feedback->load(['order', 'reservation']),
        ]);
    }
}
