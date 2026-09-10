<?php

namespace App\Services\Ai;

use OpenAI\Laravel\Facades\OpenAI;

class OpenAiService implements AiRecommender
{
    public function recommend(array $items, array $preferences): array
    {
        $available = collect($items)->keyBy('id');

        $productList = $available->map(function (array $item) {
            return sprintf(
                '- %d: %s (%s) — RM%.2f — spice: %s, protein: %s, dietary: %s, flavour: %s',
                $item['id'],
                $item['name'],
                $item['category'],
                (float) $item['price'],
                $item['spice_level'] ?? 'none',
                $item['protein_type'] ?? 'n/a',
                $item['dietary_type'] ?? 'standard',
                $item['flavor_profile'] ?? 'classic',
            );
        })->implode("\n");

        $preferenceText = collect([
            'category' => $preferences['category'] ?? null,
            'spice level' => $preferences['spice_level'] ?? null,
            'protein type' => $preferences['protein_type'] ?? null,
            'dietary type' => $preferences['dietary_type'] ?? null,
            'flavour profile' => $preferences['flavor_profile'] ?? null,
        ])->filter()->map(fn ($value, $key) => "$key: $value")->implode(', ');

        $response = OpenAI::chat()->create([
            'model' => (string) config('ai.model'),
            'temperature' => 0.4,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are Rasa AI, the menu recommendation assistant for the Le Miting restaurant. '
                        .'You recommend dishes ONLY from the provided available product list. '
                        .'Never invent dishes. Reply with JSON in this exact shape: '
                        .'{"recommended_item_ids":[<integer item ids>]}. '
                        .'Return at most '.config('ai.max_recommendations').' ids.',
                ],
                [
                    'role' => 'user',
                    'content' => "Available dishes:\n$productList\n\n"
                        .'Customer preferences: '.($preferenceText ?: 'no specific preferences')
                        ."\n\nRecommend the best-matching dishes.",
                ],
            ],
        ]);

        $content = $response->choices[0]->message->content ?? '';

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return [];
        }

        $ids = collect($decoded['recommended_item_ids'] ?? []);

        return $ids
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $available->has($id))
            ->unique()
            ->take((int) config('ai.max_recommendations'))
            ->values()
            ->all();
    }
}
