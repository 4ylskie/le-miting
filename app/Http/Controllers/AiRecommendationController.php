<?php

namespace App\Http\Controllers;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Ai\AiRecommender;
use Illuminate\Http\Request;

class AiRecommendationController extends Controller
{
    public function __construct(private AiRecommender $recommender) {}

    public function index(Request $request)
    {
        return view('customer.ai.index', [
            'categories' => MenuCategory::where('is_active', true)->orderBy('sort_order')->get(),
            'spiceLevels' => MenuItem::whereNotNull('spice_level')->distinct()->pluck('spice_level')->sort()->values(),
            'proteinTypes' => MenuItem::whereNotNull('protein_type')->distinct()->pluck('protein_type')->sort()->values(),
            'dietaryTypes' => MenuItem::whereNotNull('dietary_type')->distinct()->pluck('dietary_type')->sort()->values(),
            'flavorProfiles' => MenuItem::whereNotNull('flavor_profile')->distinct()->pluck('flavor_profile')->sort()->values(),
        ]);
    }

    public function recommend(Request $request)
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'spice_level' => ['nullable', 'string', 'max:50'],
            'protein_type' => ['nullable', 'string', 'max:50'],
            'dietary_type' => ['nullable', 'string', 'max:50'],
            'flavor_profile' => ['nullable', 'string', 'max:50'],
        ]);

        $items = MenuItem::with('category')->where('is_available', true)->get();

        if ($items->isEmpty()) {
            return view('customer.ai.results', [
                'recommendations' => collect(),
                'preferences' => $validated,
            ]);
        }

        try {
            $ids = $this->recommender->recommend(
                $items->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'category' => $item->category?->slug,
                    'price' => (float) $item->price,
                    'spice_level' => $item->spice_level,
                    'protein_type' => $item->protein_type,
                    'dietary_type' => $item->dietary_type,
                    'flavor_profile' => $item->flavor_profile,
                ])->all(),
                $validated,
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()
                ->with('error', 'Rasa AI is temporarily unavailable. Please try again or browse the menu directly.');
        }

        $orderBy = array_flip($ids);

        $recommendations = MenuItem::with('category')
            ->whereIn('id', $ids)
            ->where('is_available', true)
            ->get()
            ->sortBy(fn (MenuItem $item) => $orderBy[$item->id] ?? PHP_INT_MAX)
            ->values();

        return view('customer.ai.results', [
            'recommendations' => $recommendations,
            'preferences' => $validated,
        ]);
    }
}
