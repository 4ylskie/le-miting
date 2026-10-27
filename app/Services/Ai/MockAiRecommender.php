<?php

namespace App\Services\Ai;

class MockAiRecommender implements AiRecommender
{
    public function recommend(array $items, array $preferences): array
    {
        return collect($items)
            ->filter(function (array $item) use ($preferences): bool {
                foreach (['category', ...array_keys(AiRecommendationOptions::VALUES)] as $field) {
                    $preference = $preferences[$field] ?? null;

                    if ($preference !== null && $preference !== '' && ($item[$field] ?? null) !== $preference) {
                        return false;
                    }
                }

                if (isset($preferences['spice_levels'])
                    && ! in_array($item['spice_level'] ?? null, $preferences['spice_levels'], true)) {
                    return false;
                }

                if (isset($preferences['excluded_protein_types'])
                    && (! isset($item['protein_type']) || in_array($item['protein_type'], $preferences['excluded_protein_types'], true))) {
                    return false;
                }

                return true;
            })
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
     * Text cues rank candidates only after structured menu attributes have constrained them.
     *
     * @param  array<string, mixed>  $preferences
     */
    private function relevanceScore(array $item, array $preferences): int
    {
        $text = (string) ($preferences['preferences_text'] ?? '');
        $normalizedText = $this->normalizePhrase($text);
        $normalizedName = $this->normalizePhrase((string) ($item['name'] ?? ''));
        $score = $normalizedName !== '' && str_contains(' '.$normalizedText.' ', ' '.$normalizedName.' ')
            ? 1000
            : 0;

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
