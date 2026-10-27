<?php

namespace App\Services\Ai;

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AiRecommendationOptions
{
    private const NOT_TOO_SPICY_PATTERN = '/\bnot\s+too\s+(?:spicy|pedas)\b/u';

    private const NOT_SPICY_PATTERN = '/\b(?:not\s+(?:spicy|pedas)|non[ -]?(?:spicy|pedas)|no\s+(?:spice|pedas))\b/u';

    private const SPICY_PATTERN = '/\b(?:spicy|pedas|cili(?![^\pL\pN]+api)|chili(?:es)?|chilli(?:es)?)\b/u';

    private const HOT_PATTERN = '/\b(?:hot|cili[^\pL\pN]+api)\b/u';

    public const VALUES = [
        'spice_level' => ['none', 'mild', 'medium', 'hot'],
        'protein_type' => ['chicken', 'beef', 'seafood', 'egg', 'none'],
        'dietary_type' => ['vegetarian'],
        'flavor_profile' => ['savoury', 'sweet', 'creamy', 'tangy', 'bitter'],
    ];

    private const ALLERGEN_ALIASES = [
        'peanut' => ['peanut', 'peanuts', 'kacang tanah'],
        'tree_nuts' => ['tree nut', 'tree nuts', 'almond', 'cashew', 'walnut', 'hazelnut', 'pistachio', 'pecan', 'macadamia', 'kacang pohon', 'badam', 'gajus'],
        'milk' => ['milk', 'dairy', 'lactose', 'butter', 'cheese', 'cream', 'susu', 'tenusu', 'keju', 'mentega'],
        'egg' => ['egg', 'eggs', 'telur'],
        'soy' => ['soy', 'soya', 'tauhu', 'tempeh', 'kicap'],
        'wheat_gluten' => ['wheat', 'gluten', 'coeliac', 'celiac', 'bread', 'flour', 'gandum', 'roti', 'tepung'],
        'fish' => ['fish', 'ikan'],
        'crustacean_shellfish' => ['crustacean', 'shellfish', 'prawn', 'shrimp', 'crab', 'lobster', 'udang', 'ketam'],
        'molluscs' => ['mollusc', 'molluscs', 'mollusk', 'mollusks', 'clam', 'mussel', 'oyster', 'squid', 'octopus', 'scallop', 'kerang', 'sotong', 'tiram', 'kupang'],
        'sesame' => ['sesame', 'bijan', 'minyak bijan'],
    ];

    private const ALLERGY_TERM_PATTERN = '/(?<![\pL\pN])(?:allerg(?:y|ies|ic|ens?)|intoleran\w*|alergi|alahan)(?![\pL\pN])/iu';

    /**
     * @param  array<int, mixed>  $selectedAllergens
     * @return array{preferences: array<string, mixed>, maxPrice: ?string, priceOperator: string, notice: ?string, blocked: bool, excludedAllergens: array<int, string>}
     */
    public function interpretText(?string $input, array $selectedAllergens = []): array
    {
        $text = mb_strtolower(trim($input ?? ''));
        $textAllergens = $this->allergensInText($text);
        $excludedAllergens = array_values(array_filter(Allergen::CODES, fn (string $code): bool => in_array($code, $selectedAllergens, true)));
        $hasAllergyTerm = preg_match(self::ALLERGY_TERM_PATTERN, $text) === 1;
        $hasExclusionIntent = preg_match('/(?<![\pL\pN])(?:no|without|avoid|exclude|free\s+from|cannot\s+(?:eat|have)|can\s*not\s+(?:eat|have)|can\x27t\s+(?:eat|have)|tak\s+boleh|tidak\s+boleh|tak\s+nak|tanpa|elak(?:kan)?)(?![\pL\pN])/iu', $text) === 1
            || preg_match('/(?<![\pL\pN])(?:[\pL]+[- ]free|nut[- ]free)(?![\pL\pN])/iu', $text) === 1;

        if (($hasAllergyTerm && ($textAllergens === [] || $this->hasUnmappedAllergyTarget($text)))
            || ($hasExclusionIntent && $textAllergens === [] && $hasAllergyTerm)) {
            return [
                'preferences' => [], 'maxPrice' => null, 'priceOperator' => '<=',
                'notice' => 'We cannot verify that allergy or intolerance request from the available records. Please confirm directly with restaurant staff before ordering.',
                'blocked' => true, 'excludedAllergens' => $excludedAllergens,
            ];
        }

        if ($hasAllergyTerm || $hasExclusionIntent) {
            $excludedAllergens = array_values(array_filter(
                Allergen::CODES,
                fn (string $code): bool => in_array($code, $excludedAllergens, true) || in_array($code, $textAllergens, true),
            ));
        }

        $result = [
            'preferences' => [], 'maxPrice' => null, 'priceOperator' => '<=', 'notice' => null,
            'blocked' => false, 'excludedAllergens' => $excludedAllergens,
        ];
        if ($excludedAllergens !== []) {
            $result['preferences']['excluded_allergens'] = $excludedAllergens;
        }

        if ($text === '') {
            return $result;
        }

        $interpretationText = $text;
        $textExcludedAllergens = array_values(array_intersect($textAllergens, $excludedAllergens));
        if ($textExcludedAllergens !== []) {
            $interpretationText = preg_replace($this->allergenAliasPatterns($textExcludedAllergens), ' ', $interpretationText) ?? $interpretationText;
            $interpretationText = preg_replace('/(?<![\pL\pN])(?:nut|nuts)[- ]free(?![\pL\pN])/iu', ' ', $interpretationText) ?? $interpretationText;
            $interpretationText = preg_replace(self::ALLERGY_TERM_PATTERN, ' ', $interpretationText) ?? $interpretationText;
            $interpretationText = preg_replace('/(?<![\pL\pN])(?:to|with|for|of|pada|kepada|terhadap)(?![\pL\pN])/iu', ' ', $interpretationText) ?? $interpretationText;
        }

        if (preg_match('/\bproteins?\b/u', preg_replace('/\bno primary protein\b/u', '', $interpretationText))) {
            return [...$result, 'notice' => 'Which protein type do you mean: chicken, beef, seafood or egg? We do not have verified nutritional protein amounts. Please clarify your request.', 'blocked' => true];
        }

        $withoutNonSpicy = preg_replace('/\b(?:not\s+too\s+(?:spicy|pedas)|not\s+(?:spicy|pedas)|non[ -]?(?:spicy|pedas)|no\s+(?:spice|pedas))\b/u', '', $interpretationText);
        preg_match_all('/\b(?:no|without|avoid)\s+(chicken|ayam|beef|seafood|egg)\b/u', $interpretationText, $excluded);
        if ($excluded[1] !== []) {
            $result['preferences']['excluded_protein_types'] = array_values(array_unique(array_map(
                fn (string $protein): string => $protein === 'ayam' ? 'chicken' : $protein,
                $excluded[1],
            )));
        }
        $withoutExcludedProteins = preg_replace('/\b(?:no|without|avoid)\s+(?:chicken|ayam|beef|seafood|egg)\b/u', '', $withoutNonSpicy);

        if (preg_match('/\b(?:no|not|without|avoid)\s+(?:hot|mild|medium|spicy|pedas|cili(?:\s+api)?|chili(?:es)?|chilli(?:es)?|vegetarian|sweet|savoury|savory|creamy|tangy|bitter)\b/u', $withoutExcludedProteins)
            || preg_match('/\b(?:vegan|halal|keto|organic|pescatarian|above|at least|more than|between)\b/u', $interpretationText)) {
            return [...$result, 'notice' => 'We cannot safely verify that request from the available menu attributes. Please adjust your preferences or confirm details with restaurant staff.', 'blocked' => true];
        }

        $patterns = [
            'spice_level' => [
                'none' => self::NOT_SPICY_PATTERN,
                'mild' => '/\bmild\b/u',
                'medium' => '/\bmedium\b/u',
                'hot' => self::HOT_PATTERN,
            ],
            'protein_type' => [
                'chicken' => '/\b(?:chicken|ayam)\b/u',
                'beef' => '/\bbeef\b/u',
                'seafood' => '/\bseafood\b/u',
                'egg' => '/\begg\b/u',
                'none' => '/\bno primary protein\b/u',
            ],
            'dietary_type' => ['vegetarian' => '/\bvegetarian\b/u'],
            'flavor_profile' => [
                'savoury' => '/\b(?:savoury|savory)\b/u',
                'sweet' => '/\bsweet\b/u',
                'creamy' => '/\bcreamy\b/u',
                'tangy' => '/\btangy\b/u',
                'bitter' => '/\bbitter\b/u',
            ],
        ];

        foreach ($patterns as $field => $values) {
            $source = $field === 'protein_type' ? $withoutExcludedProteins : $interpretationText;
            $matches = array_keys(array_filter($values, fn (string $pattern): bool => (bool) preg_match($pattern, $source)));

            if (count($matches) > 1) {
                return [...$result, 'notice' => 'Your text contains conflicting preferences. Please choose one value for each preference.', 'blocked' => true];
            }

            if ($matches !== []) {
                $result['preferences'][$field] = $matches[0];
            }
        }

        $notTooSpicy = preg_match(self::NOT_TOO_SPICY_PATTERN, $interpretationText) === 1;
        $notSpicy = preg_match(self::NOT_SPICY_PATTERN, $interpretationText) === 1;
        $spicyRequest = preg_match(self::SPICY_PATTERN, $withoutNonSpicy) === 1;

        if (($notTooSpicy && (isset($result['preferences']['spice_level']) || $spicyRequest))
            || ($notSpicy && $spicyRequest)) {
            return [...$result, 'notice' => 'Your text contains conflicting spice preferences. Please clarify your request.', 'blocked' => true];
        }

        if ($notTooSpicy) {
            $result['preferences']['spice_levels'] = ['none', 'mild'];
        } elseif ($spicyRequest && ! isset($result['preferences']['spice_level'])) {
            $result['preferences']['spice_levels'] = ['medium', 'hot'];
        }

        if (isset($result['preferences']['protein_type'])
            && in_array($result['preferences']['protein_type'], $result['preferences']['excluded_protein_types'] ?? [], true)) {
            return [...$result, 'notice' => 'Your text contains conflicting protein preferences. Please clarify your request.', 'blocked' => true];
        }

        $categoryMatches = MenuCategory::query()->where('is_active', true)
            ->whereHas('menuItems', fn ($query) => $query->where('is_available', true))
            ->get(['slug', 'name'])
            ->filter(fn (MenuCategory $category): bool => (bool) preg_match('/(?<![\pL\pN])'.preg_quote(mb_strtolower($category->name), '/').'(?![\pL\pN])/u', $interpretationText));
        if ($categoryMatches->count() > 1) {
            return [...$result, 'notice' => 'Your text names more than one menu category. Please choose one.', 'blocked' => true];
        }
        if ($categoryMatches->isNotEmpty()) {
            $result['preferences']['category'] = $categoryMatches->first()->slug;
        }

        preg_match_all('/\b(under|below|less than|up to|at most|max(?:imum)?(?: price)?|budget(?: of)?)\s*(?:rm\s*)?(\d+(?:\.\d{1,2})?)(?![\d.])/u', $interpretationText, $budgets, PREG_SET_ORDER);

        if (count($budgets) > 1 || (preg_match('/\b(?:rm|budget|under|below|less than|up to|at most|max(?:imum)?)\b/u', $interpretationText) && $budgets === [])) {
            return [...$result, 'notice' => 'Please give one clear per-dish budget, such as “up to RM 20”.', 'blocked' => true];
        }

        if ($budgets !== []) {
            $amount = (float) $budgets[0][2];

            if ($amount <= 0 || $amount > 999999.99) {
                return [...$result, 'notice' => 'Please give a positive per-dish budget in RM.', 'blocked' => true];
            }

            $result['maxPrice'] = number_format($amount, 2, '.', '');
            $result['priceOperator'] = in_array($budgets[0][1], ['under', 'below', 'less than'], true) ? '<' : '<=';
        }

        if ($result['preferences'] === [] && $result['maxPrice'] === null && $result['excludedAllergens'] === []) {
            return [...$result, 'notice' => 'We could not match that wording to verified menu attributes. Please try the listed preferences or browse the menu.', 'blocked' => true];
        }

        if (($result['preferences']['dietary_type'] ?? null) === 'vegetarian'
            && in_array($result['preferences']['protein_type'] ?? null, ['chicken', 'beef', 'seafood'], true)) {
            return [...$result, 'notice' => 'Vegetarian and meat or seafood preferences conflict. Please adjust your request.', 'blocked' => true];
        }

        $unresolved = $interpretationText;
        $namedMenuItems = MenuItem::query()->pluck('name')
            ->filter(fn (string $name): bool => $this->containsPhrase($interpretationText, $name));
        foreach ($namedMenuItems as $name) {
            $unresolved = preg_replace($this->phrasePattern($name), ' ', $unresolved) ?? $unresolved;
        }
        $unresolved = preg_replace('/\b(?:not\s+too\s+(?:spicy|pedas)|not\s+(?:spicy|pedas)|non[ -]?(?:spicy|pedas)|no\s+(?:spice|pedas)|no primary protein|(?:no|without|avoid)\s+(?:chicken|ayam|beef|seafood|egg))\b/u', ' ', $unresolved);
        foreach ($patterns as $values) {
            $unresolved = preg_replace(array_values($values), ' ', $unresolved);
        }
        $unresolved = preg_replace(self::SPICY_PATTERN, ' ', $unresolved);
        foreach ($categoryMatches as $category) {
            $unresolved = preg_replace('/(?<![\pL\pN])'.preg_quote(mb_strtolower($category->name), '/').'(?![\pL\pN])/u', ' ', $unresolved);
        }
        if ($budgets !== []) {
            $unresolved = str_replace($budgets[0][0], ' ', $unresolved);
        }
        if ($result['excludedAllergens'] !== []) {
            $unresolved = preg_replace($this->allergenAliasPatterns($result['excludedAllergens']), ' ', $unresolved) ?? $unresolved;
            $unresolved = preg_replace(self::ALLERGY_TERM_PATTERN, ' ', $unresolved) ?? $unresolved;
        }
        $unresolved = preg_replace('/\b(?:i would like|i want|i would prefer|can i have|could i have|find|recommend|looking for|for me|per dish|please|something|some|a|an|the|food|dishes|dish|and|with|that|which|is|are)\b/u', ' ', $unresolved);
        if ($result['excludedAllergens'] !== []) {
            $unresolved = preg_replace('/\b(?:i|have|want|would|like|prefer|looking|to|no|without|avoid|exclude|free|from|and|or|elak|elakkan|tanpa|saya|tidak|tak|boleh|nak)\b/u', ' ', $unresolved) ?? $unresolved;
        }

        if (preg_match('/[\pL\pN]/u', $unresolved)) {
            return [...$result, 'notice' => 'Part of your request could not be matched to verified menu attributes. Please clarify using a protein type, spice level, flavour, vegetarian preference, menu category or per-dish budget, or browse the menu.', 'blocked' => true];
        }

        $result['notice'] = $result['excludedAllergens'] === []
            ? 'Only recognized menu attributes and any stated per-dish price limit were applied. A protein label does not verify ingredients or allergens; please confirm these with restaurant staff.'
            : null;

        return $result;
    }

    /** @return array<int, string> */
    private function allergensInText(string $text): array
    {
        $matches = [];

        foreach (self::ALLERGEN_ALIASES as $code => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match($this->allergenMentionPattern($alias), $text) === 1) {
                    $matches[] = $code;

                    break;
                }
            }
        }

        if (preg_match('/(?<![\pL\pN])(?:nut|nuts)[- ]free(?![\pL\pN])/iu', $text) === 1) {
            $matches[] = 'peanut';
            $matches[] = 'tree_nuts';
        }

        return array_values(array_filter(Allergen::CODES, fn (string $code): bool => in_array($code, $matches, true)));
    }

    /** @param array<int, string> $codes */
    private function allergenAliasPatterns(array $codes): array
    {
        $aliases = [];
        foreach ($codes as $code) {
            array_push($aliases, ...(self::ALLERGEN_ALIASES[$code] ?? []));
        }
        usort($aliases, fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));

        return [...array_map(fn (string $alias): string => $this->allergenMentionPattern($alias), $aliases), '/(?<![\pL\pN])(?:nut|nuts)[- ]free(?![\pL\pN])/iu'];
    }

    private function allergenMentionPattern(string $alias): string
    {
        $pattern = substr($this->phrasePattern($alias), 0, -2);

        return $pattern.'(?:[- ]free)?(?![\pL\pN])/iu';
    }

    private function hasUnmappedAllergyTarget(string $text): bool
    {
        $targets = [];
        preg_match_all('/(?<![\pL\pN])(?:allerg(?:y|ies|ic|ens?)|intoleran\w*|alergi|alahan)\s+(?:to|with|for|of|pada|kepada|terhadap)\s+([^,;.!?]+)/iu', $text, $forwardMatches);
        preg_match_all('/(?<![\pL\pN])([\pL\pN]+(?:[\s-]+[\pL\pN]+){0,2})\s+(?:allerg(?:y|ies|ic|ens?)|intoleran\w*)(?![\pL\pN])/iu', $text, $reverseMatches);
        $targets = [...$forwardMatches[1], ...$reverseMatches[1]];

        foreach ($targets as $target) {
            $parts = preg_split('/\b(?:and|or|dan|atau)\b|[,;]+/iu', $target) ?: [];

            foreach ($parts as $part) {
                $part = trim(preg_replace('/\b(?:i|am|have|a|an|the|my|severe|mild|very)\b/iu', ' ', $part) ?? '');

                if ($part !== '' && $this->allergensInText($part) === []) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function label(string $field, string $value): string
    {
        if ($value === 'none') {
            return $field === 'spice_level' ? 'Not Spicy' : 'No primary protein';
        }

        return ucfirst($value);
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        $normalizedText = $this->normalizePhrase($text);
        $normalizedPhrase = $this->normalizePhrase($phrase);

        return $normalizedPhrase !== '' && str_contains(' '.$normalizedText.' ', ' '.$normalizedPhrase.' ');
    }

    private function normalizePhrase(string $value): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', mb_strtolower($value)) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
    }

    private function phrasePattern(string $phrase): string
    {
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($phrase), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $quotedTokens = array_map(fn (string $token): string => preg_quote($token, '/'), $tokens);

        return '/(?<![\pL\pN])'.implode('[^\pL\pN]+', $quotedTokens).'(?![\pL\pN])/u';
    }

    /** @return Collection<int, string> */
    public function availableValues(string $field): Collection
    {
        $stored = MenuItem::query()
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true))
            ->whereIn($field, self::VALUES[$field])
            ->distinct()->pluck($field);

        return collect(self::VALUES[$field])->intersect($stored)->values();
    }

    /** @param array<string, mixed> $preferences */
    public function filter(Builder $query, array $preferences): Builder
    {
        foreach (array_keys(self::VALUES) as $field) {
            if (isset($preferences[$field]) && $preferences[$field] !== '') {
                $query->where($field, $preferences[$field]);
            }
        }

        if (isset($preferences['spice_levels'])) {
            $query->whereIn('spice_level', $preferences['spice_levels']);
        }

        if (isset($preferences['excluded_protein_types'])) {
            $query->whereNotNull('protein_type')->whereNotIn('protein_type', $preferences['excluded_protein_types']);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function forForm(): array
    {
        return [
            'categories' => MenuCategory::query()
                ->where('is_active', true)
                ->whereHas('menuItems', fn ($query) => $query->where('is_available', true))
                ->orderBy('sort_order')
                ->get(),
            'spiceLevels' => $this->availableValues('spice_level'),
            'proteinTypes' => $this->availableValues('protein_type'),
            'dietaryTypes' => $this->availableValues('dietary_type'),
            'flavorProfiles' => $this->availableValues('flavor_profile'),
        ];
    }
}
