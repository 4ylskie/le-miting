<?php

namespace App\Services;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;

class ReservationMenuCandidates
{
    /**
     * Temporary Level 2 candidates, not an approved preorderable-dish policy.
     * Apply the restaurant's explicit preorder eligibility rule here once approved.
     *
     * @return Builder<MenuItem>
     */
    public function query(): Builder
    {
        return MenuItem::query()
            ->where('is_available', true)
            ->whereHas('category', fn (Builder $query) => $query->where('is_active', true));
    }
}
