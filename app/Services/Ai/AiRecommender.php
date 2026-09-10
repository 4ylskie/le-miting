<?php

namespace App\Services\Ai;

interface AiRecommender
{
    /**
     * Return the ids of recommended available menu items.
     *
     * @param  array<int, array{id: int, name: string, description: string, category: string, price: float, spice_level: ?string, protein_type: ?string, dietary_type: ?string, flavor_profile: ?string}>  $items
     * @param  array<string, ?string>  $preferences
     * @return array<int, int>
     */
    public function recommend(array $items, array $preferences): array;
}
