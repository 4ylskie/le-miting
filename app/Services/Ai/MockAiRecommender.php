<?php

namespace App\Services\Ai;

class MockAiRecommender implements AiRecommender
{
    public function recommend(array $items, array $preferences): array
    {
        $options = new AiRecommendationOptions;
        $eligible = collect($items)->filter(fn (array $item): bool => $options->matchesHardPreferences($item, $preferences));
        $level = $eligible->max(fn (array $item): int => $options->softMatchLevel($item, $preferences)) ?? 0;

        return $eligible
            ->filter(fn (array $item): bool => $options->softMatchLevel($item, $preferences) >= $level)
            ->sort(function (array $left, array $right) use ($preferences): int {
                $score = $this->relevanceScore($right, $preferences) <=> $this->relevanceScore($left, $preferences);

                return $score !== 0 ? $score : ((int) $left['id'] <=> (int) $right['id']);
            })
            ->pluck('id')
            ->take(min(3, (int) config('ai.max_recommendations')))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Text cues rank candidates only after hard eligibility and preferred soft matches.
     *
     * @param  array<string, mixed>  $preferences
     */
    private function relevanceScore(array $item, array $preferences): int
    {
        $text = (string) ($preferences['preferences_text'] ?? '');
        $normalizedText = $this->normalizePhrase($text);
        $normalizedName = $this->normalizePhrase((string) ($item['name'] ?? ''));
        $score = (new AiRecommendationOptions)->softMatchCount($item, $preferences) * 100;
        if ($normalizedName !== '' && str_contains(' '.$normalizedText.' ', ' '.$normalizedName.' ')) {
            $score += 1000;
        }

        if (($preferences['flavor_profile'] ?? null) === 'creamy'
            && ! isset($preferences['spice_level']) && ! isset($preferences['spice_levels'])) {
            $score += match ($item['spice_level'] ?? null) {
                'none' => 3,
                'mild' => 2,
                'medium' => 1,
                default => 0,
            };
        }

        $spicyIntent = isset($preferences['spice_levels'])
            || in_array($preferences['spice_level'] ?? null, ['medium', 'hot'], true)
            || preg_match('/\b(?:spicy|pedas|cili|chili(?:es)?|chilli(?:es)?)\b/u', $text) === 1;

        if (! $spicyIntent) {
            return $score;
        }

        $description = $this->normalizePhrase((string) ($item['description'] ?? ''));
        $searchableText = ' '.$normalizedName.' '.$description.' ';

        if (preg_match('/\bcili\s+api\b/u', $searchableText) === 1) {
            $score += 8;
        }

        preg_match_all('/\b(?:cili|chili(?:es)?|chilli(?:es)?|pedas)\b/u', $searchableText, $chiliMatches);
        $score += count($chiliMatches[0]) * 3;

        if (preg_match('/\bsambal\b/u', $searchableText) === 1) {
            $score++;
        }

        if (preg_match('/\bspic(?:e|es|y)\b/u', $searchableText) === 1) {
            $score++;
        }

        return $score;
    }

    private function normalizePhrase(string $value): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
    }
}
