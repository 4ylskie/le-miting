<?php

namespace App\Services;

use App\Models\Allergen;
use App\Models\MenuItem;
use App\Models\MenuItemAllergenAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MenuAllergenAssessmentService
{
    private const MENU_CONTENT_TERMS = [
        'peanut' => ['peanut', 'peanuts', 'groundnut', 'groundnuts', 'satay sauce', 'kuah kacang', 'kacang tanah'],
        'tree_nuts' => ['almond', 'almonds', 'cashew', 'cashews', 'walnut', 'walnuts', 'hazelnut', 'hazelnuts', 'pistachio', 'pistachios', 'pecan', 'pecans', 'macadamia', 'pine nut', 'pine nuts', 'praline', 'badam', 'gajus', 'kacang pohon'],
        'milk' => ['milk', 'dairy', 'cheese', 'butter', 'cream', 'creamy', 'yogurt', 'yoghurt', 'whey', 'casein', 'ghee', 'mascarpone', 'mozzarella', 'parmesan', 'cheddar', 'susu', 'keju', 'mentega', 'berkrim', 'krim', 'tenusu'],
        'egg' => ['egg', 'eggs', 'mayo', 'mayonnaise', 'meringue', 'custard', 'hollandaise', 'yolk', 'albumen', 'telur'],
        'soy' => ['soy', 'soya', 'tofu', 'bean curd', 'tempeh', 'edamame', 'miso', 'shoyu', 'teriyaki', 'soy sauce', 'tauhu', 'kicap'],
        'wheat_gluten' => ['wheat', 'gluten', 'bread', 'flour', 'pasta', 'spaghetti', 'fettuccine', 'penne', 'lasagna', 'noodle', 'noodles', 'mee', 'bun', 'toast', 'breadcrumbs', 'panko', 'tempura', 'pastry', 'cake', 'crust', 'crouton', 'soy sauce', 'gandum', 'roti', 'tepung'],
        'fish' => ['fish', 'salmon', 'cod', 'tuna', 'seabass', 'siakap', 'snapper', 'anchovy', 'anchovies', 'ikan', 'bilis', 'keropok lekor'],
        'crustacean_shellfish' => ['prawn', 'prawns', 'shrimp', 'crab', 'lobster', 'crawfish', 'crayfish', 'scampi', 'shell', 'shellfish', 'udang', 'ketam', 'belacan'],
        'molluscs' => ['mollusc', 'molluscs', 'clam', 'clams', 'mussel', 'mussels', 'oyster', 'oysters', 'squid', 'calamari', 'octopus', 'scallop', 'scallops', 'cockle', 'kerang', 'sotong', 'tiram', 'kupang', 'lala'],
        'sesame' => ['sesame', 'tahini', 'goma', 'sesame oil', 'bijan', 'minyak bijan'],
    ];

    private const MENU_CONTENT_NEGATION_PATTERN = '/(?:^|[^\pL\pN])(?:no|without|avoid|exclude|excluding|free\s+from|free\s+of|does\s+not\s+contain|doesn[\'’]t\s+contain|not\s+contain|contains\s+no|tanpa|bebas)(?:\s+[\pL\pN]+){0,3}\s*$/iu';

    /** @param array<int, string> $allergenCodes */
    public function isEligibleForAllergenExclusions(MenuItem $item, array $allergenCodes): bool
    {
        if ($allergenCodes === []) {
            return true;
        }

        if (array_diff($allergenCodes, Allergen::CODES) !== []) {
            return false;
        }

        $item->loadMissing(['allergenAssessments.allergen', 'category']);
        $assessments = $item->allergenAssessments->keyBy(fn (MenuItemAllergenAssessment $assessment): ?string => $assessment->allergen?->code);

        foreach ($allergenCodes as $code) {
            $assessment = $assessments->get($code);

            if ($assessment instanceof MenuItemAllergenAssessment) {
                if (! $assessment->isCurrentFor($item)
                    || $assessment->recipe_status !== 'reviewed_absent'
                    || $assessment->cross_contact_status !== 'reviewed_no_known_risk') {
                    return false;
                }

                continue;
            }

            if ($this->menuTextMatchesAllergen($item, $code)) {
                return false;
            }
        }

        return true;
    }

    private function menuTextMatchesAllergen(MenuItem $item, string $allergenCode): bool
    {
        $terms = self::MENU_CONTENT_TERMS[$allergenCode] ?? [];

        if ($terms === []) {
            return true;
        }

        usort($terms, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));
        $termPattern = implode('|', array_map(
            fn (string $term): string => str_replace(' ', '[\\s-]+', preg_quote($term, '/')),
            $terms,
        ));
        $text = mb_strtolower(implode(' ', array_filter([
            $item->name,
            $item->description,
            $item->category?->name,
        ])));

        if ($allergenCode === 'wheat_gluten'
            && preg_match('/(?<![\pL\pN])bihun(?![\pL\pN])/iu', $text) === 1
            && preg_match('/(?<![\pL\pN])(?:wheat|gandum)(?![\pL\pN])/iu', $text) === 1) {
            return true;
        }

        preg_match_all('/(?<![\pL\pN])('.$termPattern.')(?![\pL\pN])/iu', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$matchedTerm, $byteOffset]) {
            $prefixLength = mb_strlen(substr($text, 0, $byteOffset), 'UTF-8');
            $prefix = mb_substr($text, 0, $prefixLength, 'UTF-8');
            $suffix = mb_substr($text, $prefixLength + mb_strlen($matchedTerm, 'UTF-8'), null, 'UTF-8');

            if (preg_match(self::MENU_CONTENT_NEGATION_PATTERN, $prefix) === 1
                || preg_match('/^[- ]+free\b/iu', $suffix) === 1) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function context(MenuItem $item, ?MenuItemAllergenAssessment $assessment): string
    {
        return hash('sha256', json_encode([
            MenuItemAllergenAssessment::menuFingerprint($item), $assessment?->getAttributes(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array{recipe_status: string, cross_contact_status: string, evidence: string, context: string} $data */
    public function save(MenuItem $item, Allergen $allergen, User $reviewer, array $data): void
    {
        abort_unless($reviewer->isAdmin(), 403);
        DB::transaction(function () use ($item, $allergen, $reviewer, $data): void {
            $locked = MenuItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $assessment = $locked->allergenAssessments()->where('allergen_id', $allergen->id)->first();
            if (! hash_equals($this->context($locked, $assessment), $data['context'])) {
                throw ValidationException::withMessages(['context' => 'The menu or assessment changed. Reload this page and review the current information.']);
            }
            $locked->allergenAssessments()->updateOrCreate(['allergen_id' => $allergen->id], [
                'recipe_status' => $data['recipe_status'], 'cross_contact_status' => $data['cross_contact_status'],
                'evidence' => trim($data['evidence']), 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(),
                'menu_fingerprint' => MenuItemAllergenAssessment::menuFingerprint($locked),
                'invalidated_at' => null, 'invalidated_by' => null, 'invalidation_reason' => null,
            ]);
        });
    }

    public function invalidate(MenuItem $item, User $reviewer, string $reason): void
    {
        abort_unless($reviewer->isAdmin(), 403);
        DB::transaction(function () use ($item, $reviewer, $reason): void {
            $locked = MenuItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->increment('allergen_assessment_revision');
            $locked->allergenAssessments()->whereNull('invalidated_at')->update([
                'invalidated_at' => now(), 'invalidated_by' => $reviewer->id, 'invalidation_reason' => $reason,
            ]);
        });
    }
}
