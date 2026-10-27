<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemAllergenAssessment extends Model
{
    public const RECIPE = ['unknown' => 'Unknown', 'contains' => 'Contains', 'reviewed_absent' => 'Reviewed absent'];

    public const CROSS_CONTACT = ['unknown' => 'Unknown', 'possible' => 'Possible', 'reviewed_no_known_risk' => 'Reviewed no known risk'];

    public const RELEVANT_FIELDS = ['name', 'description', 'menu_category_id', 'spice_level', 'protein_type', 'dietary_type', 'flavor_profile'];

    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['reviewed_at' => 'immutable_datetime', 'invalidated_at' => 'immutable_datetime'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function invalidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }

    public static function menuFingerprint(MenuItem $item): string
    {
        return hash('sha256', json_encode([
            ...$item->only(self::RELEVANT_FIELDS),
            'allergen_assessment_revision' => (int) $item->allergen_assessment_revision,
        ], JSON_THROW_ON_ERROR));
    }

    public function isCurrentFor(MenuItem $item): bool
    {
        return $this->invalidated_at === null && $this->reviewed_by !== null
            && $this->reviewed_at !== null && trim((string) $this->evidence) !== ''
            && hash_equals(self::menuFingerprint($item), (string) $this->menu_fingerprint);
    }
}
