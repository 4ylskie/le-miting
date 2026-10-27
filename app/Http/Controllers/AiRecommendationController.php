<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecommendMenuRequest;
use App\Models\Allergen;
use App\Models\MenuItem;
use App\Services\Ai\AiRecommendationOptions;
use App\Services\Ai\AiRecommender;
use App\Services\MenuAllergenAssessmentService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AiRecommendationController extends Controller
{
    public function __construct(
        private AiRecommender $recommender,
        private AiRecommendationOptions $options,
        private MenuAllergenAssessmentService $assessmentService,
    ) {}

    public function index(Request $request): View
    {
        $storedAllergens = $request->session()->get('rasa_ai.preferences.excluded_allergens', []);

        return view('customer.ai.index', [
            'preferences' => $request->session()->get('rasa_ai.preferences', []),
            'selectedAllergens' => is_array($storedAllergens) ? $storedAllergens : [],
            'allergens' => Allergen::query()->whereIn('code', Allergen::CODES)->orderBy('sort_order')->get(),
            'isMock' => config('ai.driver') === 'mock',
        ]);
    }

    public function recommend(RecommendMenuRequest $request): View|RedirectResponse
    {
        $validated = $request->validated();
        $text = is_string($validated['preferences_text'] ?? null) ? $validated['preferences_text'] : null;
        $selectedAllergens = $validated['excluded_allergens'] ?? [];
        $request->session()->put('rasa_ai.preferences', [
            'preferences_text' => $text,
            'excluded_allergens' => $selectedAllergens,
        ]);

        $interpreted = $this->options->interpretText($text, $selectedAllergens);
        $preferences = $interpreted['preferences'];
        $excludedAllergens = Allergen::query()
            ->whereIn('code', $interpreted['excludedAllergens'])
            ->orderBy('sort_order')
            ->get();

        if ($interpreted['blocked']) {
            return $this->resultsView(collect(), $preferences, $interpreted['notice'], null, $excludedAllergens);
        }

        $itemsQuery = MenuItem::query()
            ->with('category')
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true));

        if ($interpreted['excludedAllergens'] !== []) {
            $itemsQuery->with('allergenAssessments.allergen');
        }

        if ($category = $preferences['category'] ?? null) {
            $itemsQuery->whereHas('category', fn ($query) => $query->where('slug', $category));
        }

        if ($interpreted['maxPrice'] !== null) {
            $itemsQuery->where('price', $interpreted['priceOperator'], $interpreted['maxPrice']);
        }

        $items = $this->options->filter($itemsQuery, $preferences)->get();
        if ($interpreted['excludedAllergens'] !== []) {
            $items = $items->filter(fn (MenuItem $item): bool => $this->assessmentService
                ->isEligibleForAllergenExclusions($item, $interpreted['excludedAllergens']))->values();
        }

        if ($items->isEmpty()) {
            $notice = $interpreted['notice'];

            if ($interpreted['excludedAllergens'] !== []) {
                $names = $excludedAllergens->pluck('name')->join(', ');
                $notice = "No available dishes match your preferences after applying menu-text checks and any current admin allergen assessments for {$names}. Please confirm ingredients and cross-contact handling directly with restaurant staff before ordering.";
            }

            return $this->resultsView(collect(), $preferences, $notice, $interpreted['maxPrice'], $excludedAllergens);
        }

        try {
            $ids = $this->recommender->recommend(
                $items->map(static fn (MenuItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'category' => $item->category?->slug ?? '',
                    'price' => (float) $item->price,
                    'spice_level' => $item->spice_level,
                    'protein_type' => $item->protein_type,
                    'dietary_type' => $item->dietary_type,
                    'flavor_profile' => $item->flavor_profile,
                ])->all(),
                [...$preferences, 'preferences_text' => $text ?? ''],
            );
        } catch (\Throwable) {
            Log::warning('Rasa AI recommendation provider failed.');

            return back()->withInput()
                ->with('error', 'Rasa AI is temporarily unavailable. Please try again or browse the menu directly.');
        }

        $orderBy = array_flip($ids);

        $recommendationsQuery = MenuItem::query()
            ->with('category')
            ->whereIn('id', $ids)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true));

        if ($interpreted['excludedAllergens'] !== []) {
            $recommendationsQuery->with('allergenAssessments.allergen');
        }

        if ($category = $preferences['category'] ?? null) {
            $recommendationsQuery->whereHas('category', fn ($query) => $query->where('slug', $category));
        }

        if ($interpreted['maxPrice'] !== null) {
            $recommendationsQuery->where('price', $interpreted['priceOperator'], $interpreted['maxPrice']);
        }

        $recommendations = $this->options->filter($recommendationsQuery, $preferences)->get()
            ->filter(fn (MenuItem $item): bool => $this->assessmentService
                ->isEligibleForAllergenExclusions($item, $interpreted['excludedAllergens']))
            ->sortBy(fn (MenuItem $item) => $orderBy[$item->id] ?? PHP_INT_MAX)
            ->take((int) config('ai.max_recommendations'))
            ->values();

        return $this->resultsView($recommendations, $preferences, $interpreted['notice'], $interpreted['maxPrice'], $excludedAllergens);
    }

    /** @param Collection<int, MenuItem> $recommendations
     * @param  array<string, mixed>  $preferences
     * @param  EloquentCollection<int, Allergen>  $excludedAllergens
     */
    private function resultsView(Collection $recommendations, array $preferences, ?string $notice, ?string $maxPrice, EloquentCollection $excludedAllergens): View
    {
        return view('customer.ai.results', compact('recommendations', 'preferences', 'notice', 'maxPrice', 'excludedAllergens'));
    }
}
