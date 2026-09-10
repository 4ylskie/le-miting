<?php

namespace App\Services\Ai;

class MockAiRecommender implements AiRecommender
{
    public function recommend(array $items, array $preferences): array
    {
        $scored = collect($items)->map(function (array $item) use ($preferences) {
            $score = 0;

            if (isset($preferences['category'])
                && $preferences['category']
                && strtolower((string) $item['category']) === strtolower((string) $preferences['category'])) {
                $score += 3;
            }

            foreach (['spice_level', 'protein_type', 'dietary_type', 'flavor_profile'] as $field) {
                $preference = $preferences[$field] ?? null;

                if ($preference && ($item[$field] ?? null)
                    && strtolower((string) $item[$field]) === strtolower((string) $preference)) {
                    $score += 2;
                }
            }

            return ['id' => $item['id'], 'score' => $score];
        });

        return $scored
            ->reject(fn ($entry) => $entry['score'] === 0)
            ->sortByDesc('score')
            ->merge($scored->where('score', 0)->shuffle())
            ->pluck('id')
            ->take((int) config('ai.max_recommendations'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
