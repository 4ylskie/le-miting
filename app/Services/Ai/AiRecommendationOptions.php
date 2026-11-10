<?php

namespace App\Services\Ai;

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AiRecommendationOptions
{
    private const NOT_TOO_SPICY_PATTERN = '/\b(?:not\s+too|(?:tak|tidak)\s+terlalu)\s+(?:spicy|pedas)\b/u';

    private const NOT_SPICY_PATTERN = '/\b(?:(?:not|tak|tidak)\s+(?:spicy|pedas)|non[ -]?(?:spicy|pedas)|no\s+(?:spice|pedas))\b/u';

    private const SPICY_PATTERN = '/\b(?:spicy|pedas|cili(?![^\pL\pN]+api)|chili(?:es)?|chilli(?:es)?)\b/u';

    private const HOT_PATTERN = '/\b(?:hot|cili[^\pL\pN]+api)\b/u';

    public const VALUES = [
        'spice_level' => ['none', 'mild', 'medium', 'hot'],
        'protein_type' => ['chicken', 'beef', 'seafood', 'egg', 'none'],
        'dietary_type' => ['vegetarian'],
        'flavor_profile' => ['savoury', 'sweet', 'creamy', 'tangy', 'bitter'],
    ];

    /** Taste and cooking cues only; these never establish allergen or dietary eligibility. */
    private const CULINARY_PATTERNS = [
        'creamy' => '/\b(?:creamy|berkrim|krim|lemak|santan|percik|coconut|kaya|butter|mentega|cheese|keju|cream|milk|susu|lodeh)\b/u',
        'savoury' => '/\b(?:savoury|savory|gurih|umami|spiced|berempah|sambal|belacan|satay|sate|rendang|percik|curry|kari|soup|sup|fried|goreng|grilled|bakar|panggang)\b/u',
        'sweet' => '/\b(?:sweet|manis|sugar|gula|syrup|sirap|kaya|cake|dessert|cendol|bandung|kuih)\b/u',
        'tangy' => '/\b(?:tangy|sour|masam|asam|lime|limau|lemon)\b/u',
        'bitter' => '/\b(?:bitter|pahit|black coffee|black tea|kopi o|teh o)\b/u',
        'grilled' => '/\b(?:grilled|grill|char[ -]grilled|bakar|panggang|satay|sate|percik|bbq|barbecue)\b/u',
        'fried' => '/\b(?:fried|fry|goreng|begedil)\b/u',
        'steamed' => '/\b(?:steamed|steam|kukus)\b/u',
        'slow_cooked' => '/\b(?:slow[ -]cooked|simmered|simmer|rendang|rebus|braised)\b/u',
        'crispy' => '/\b(?:crispy|crunchy|rangup|garing)\b/u',
        'spiced' => '/\b(?:spicy|spiced|spices|berempah|pedas|cili|chili|chillies|chili peppers|sambal|rendang|percik)\b/u',
        'rice' => '/\b(?:rice(?![ -]+cake)|nasi)\b/u',
        'noodles' => '/\b(?:noodles?|mee|mi|bihun|kuey teow|kuetiau)\b/u',
        'bread' => '/\b(?:bread|flatbread|roti|naan)\b/u',
        'vegetables' => '/\b(?:vegetables?|sayur|ulam|kangkung|spinach|potato|kentang|mung beans|kacang hijau)\b/u',
        'fish' => '/\b(?:fish|ikan|mackerel|kembung|salmon|anchovies|bilis)\b/u',
        'prawns' => '/\b(?:prawns?|shrimp|udang)\b/u',
        'lamb' => '/\b(?:lamb|mutton|kambing)\b/u',
        'squid' => '/\b(?:squid|sotong)\b/u',
        'coffee' => '/\b(?:coffee|kopi)\b/u',
        'tea' => '/\b(?:tea|teh)\b/u',
        'chocolate' => '/\b(?:chocolate|coklat)\b/u',
        'milk' => '/\b(?:milk|susu)\b/u',
        'butter' => '/\b(?:butter|mentega)\b/u',
        'cheese' => '/\b(?:cheese|keju)\b/u',
        'kaya' => '/\bkaya\b/u',
        'coconut' => '/\b(?:coconut|santan)\b/u',
    ];

    private const CATEGORY_PATTERNS = [
        'appetizers' => '/\b(?:appetizers?|starters?|pembuka selera)\b/u',
        'main-course' => '/\b(?:main[ -]courses?|hidangan utama|makanan utama)\b/u',
        'grills' => '/\bgrills\b/u',
        'soups' => '/\b(?:soups?|sup)\b/u',
        'desserts' => '/\b(?:desserts?|pencuci mulut|manisan)\b/u',
        'beverages' => '/\b(?:beverages?|drinks?|minuman)\b/u',
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

        $withoutNonSpicy = preg_replace([self::NOT_TOO_SPICY_PATTERN, self::NOT_SPICY_PATTERN], '', $interpretationText);
        preg_match_all('/\b(?:no|without|avoid)\s+(chicken|ayam|beef|seafood|egg)\b/u', $interpretationText, $excluded);
        if ($excluded[1] !== []) {
            $result['preferences']['excluded_protein_types'] = array_values(array_unique(array_map(
                fn (string $protein): string => $protein === 'ayam' ? 'chicken' : $protein,
                $excluded[1],
            )));
        }
        $withoutExcludedProteins = preg_replace('/\b(?:no|without|avoid)\s+(?:chicken|ayam|beef|seafood|egg)\b/u', '', $withoutNonSpicy);

        if (preg_match('/\b(?:no|not|without|avoid)\s+(?:hot|mild|medium|spicy|pedas|cili(?:\s+api)?|chili(?:es)?|chilli(?:es)?|vegetarian|sweet|savoury|savory|creamy|tangy|bitter|berkrim|manis|masam|pahit|bakar|panggang|goreng|grilled|fried|steamed|crispy|crunchy|spiced|rice|noodles?|bread|vegetables?|coffee|tea|chocolate|kaya|coconut)\b/u', $withoutExcludedProteins)
            || preg_match('/\b(?:tak|tidak|bukan|jangan)\s+[\pL]/u', $withoutExcludedProteins)
            || preg_match('/\b(?:vegan|halal|keto|organic|pescatarian|above|at least|more than|between|atas|lebih daripada|diabet\w*|hamil|rendah gula|darah tinggi)\b/u', $interpretationText)) {
            return [...$result, 'notice' => 'We cannot safely verify that request from the available menu attributes. Please adjust your preferences or confirm details with restaurant staff.', 'blocked' => true];
        }

        $catalog = MenuItem::query()->get(['name', 'description', 'flavor_profile', 'spice_level']);
        $namedMenuItems = $catalog->pluck('name')
            ->filter(fn (string $name): bool => $this->containsPhrase($interpretationText, $name));
        $flavorText = $interpretationText;
        foreach ($namedMenuItems as $name) {
            $flavorText = preg_replace($this->phrasePattern($name), ' ', $flavorText) ?? $flavorText;
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
                'beef' => '/\b(?:beef|daging|lembu)\b/u',
                'seafood' => '/\b(?:seafood|makanan laut|fish|ikan|prawns?|shrimp|udang|sotong|squid)\b/u',
                'egg' => '/\b(?:eggs?|telur)\b/u',
                'none' => '/\bno primary protein\b/u',
            ],
            'dietary_type' => ['vegetarian' => '/\bvegetarian\b/u'],
            'flavor_profile' => [
                'savoury' => '/\b(?:savoury|savory|gurih|umami)\b/u',
                'sweet' => '/\b(?:sweet|manis)\b/u',
                'creamy' => '/\b(?:creamy|berkrim|krim|cream|lemak|santan)\b/u',
                'tangy' => '/\b(?:tangy|sour|masam|asam)\b/u',
                'bitter' => '/\b(?:bitter|pahit)\b/u',
            ],
        ];

        foreach ($patterns as $field => $values) {
            $source = match ($field) {
                'protein_type' => $withoutExcludedProteins,
                'flavor_profile' => $flavorText,
                default => $interpretationText,
            };
            $matches = array_keys(array_filter($values, fn (string $pattern): bool => (bool) preg_match($pattern, $source)));

            if (count($matches) > 1) {
                return [...$result, 'notice' => 'Your text contains conflicting preferences. Please choose one value for each preference.', 'blocked' => true];
            }

            if ($matches !== []) {
                $result['preferences'][$field] = $matches[0];
            }
        }

        foreach ([
            'fish' => '/\b(?:fish|ikan|salmon|mackerel|kembung)\b/u',
            'prawns' => '/\b(?:prawns?|shrimp|udang)\b/u',
            'lamb' => '/\b(?:lamb|mutton|kambing)\b/u',
            'squid' => '/\b(?:squid|sotong)\b/u',
        ] as $term => $pattern) {
            if (preg_match($pattern, $withoutExcludedProteins) === 1) {
                $result['preferences']['required_terms'][] = $term;
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

        $categoryMatches = MenuCategory::query()->get(['slug', 'name'])
            ->filter(fn (MenuCategory $category): bool => $this->containsPhrase($interpretationText, $category->name)
                || preg_match(self::CATEGORY_PATTERNS[$category->slug] ?? '/(?!)/u', $interpretationText) === 1);
        $categorySlugs = collect(self::CATEGORY_PATTERNS)
            ->filter(fn (string $pattern): bool => preg_match($pattern, $interpretationText) === 1)
            ->keys()->merge($categoryMatches->pluck('slug'))->unique()->values();
        if ($categorySlugs->count() > 1) {
            return [...$result, 'notice' => 'Your text names more than one menu category. Please choose one.', 'blocked' => true];
        }
        if ($categorySlugs->isNotEmpty()) {
            $result['preferences']['category'] = $categorySlugs->first();
        }

        preg_match_all('/\b(under|below|less than|up to|at most|max(?:imum)?(?: price)?|budget(?: of)?|bawah|kurang(?: daripada| dari)?|bajet|maksimum|harga maksimum)\s*(?:rm\s*)?(\d+(?:\.\d{1,2})?)(?!\d|\.\d)/u', $interpretationText, $budgets, PREG_SET_ORDER);

        if (count($budgets) > 1 || (preg_match('/\b(?:rm|budget|under|below|less than|up to|at most|max(?:imum)?|bawah|kurang|bajet|maksimum|harga|lebih)\b/u', $interpretationText) && $budgets === [])) {
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

        if (($result['preferences']['dietary_type'] ?? null) === 'vegetarian'
            && in_array($result['preferences']['protein_type'] ?? null, ['chicken', 'beef', 'seafood'], true)) {
            return [...$result, 'notice' => 'Vegetarian and meat or seafood preferences conflict. Please adjust your request.', 'blocked' => true];
        }

        $unresolved = $interpretationText;
        foreach ($namedMenuItems as $name) {
            $unresolved = preg_replace($this->phrasePattern($name), ' ', $unresolved) ?? $unresolved;
        }
        $unresolved = preg_replace('/\b(?:not\s+too\s+(?:spicy|pedas)|not\s+(?:spicy|pedas)|non[ -]?(?:spicy|pedas)|no\s+(?:spice|pedas)|no primary protein|(?:no|without|avoid)\s+(?:chicken|ayam|beef|seafood|egg))\b/u', ' ', $unresolved);
        $unresolved = preg_replace([self::NOT_TOO_SPICY_PATTERN, self::NOT_SPICY_PATTERN], ' ', $unresolved);
        foreach ($patterns as $values) {
            $unresolved = preg_replace(array_values($values), ' ', $unresolved);
        }
        $unresolved = preg_replace(self::SPICY_PATTERN, ' ', $unresolved);
        foreach ($categoryMatches as $category) {
            $unresolved = preg_replace($this->phrasePattern($category->name), ' ', $unresolved);
        }
        foreach ($categorySlugs as $slug) {
            $unresolved = preg_replace(self::CATEGORY_PATTERNS[$slug] ?? '/(?!)/u', ' ', $unresolved);
        }
        if ($budgets !== []) {
            $unresolved = str_replace($budgets[0][0], ' ', $unresolved);
        }
        if ($result['excludedAllergens'] !== []) {
            $unresolved = preg_replace($this->allergenAliasPatterns($result['excludedAllergens']), ' ', $unresolved) ?? $unresolved;
            $unresolved = preg_replace(self::ALLERGY_TERM_PATTERN, ' ', $unresolved) ?? $unresolved;
        }
        foreach (self::CULINARY_PATTERNS as $keyword => $pattern) {
            if (in_array($keyword, self::VALUES['flavor_profile'], true)
                || preg_match($pattern, $unresolved) !== 1
                || ! $catalog->contains(fn (MenuItem $item): bool => in_array($keyword, $this->culinaryTraits($item->getAttributes()), true))) {
                continue;
            }

            $key = in_array($keyword, ['rice', 'noodles', 'bread', 'fish', 'prawns', 'lamb', 'coffee', 'tea', 'chocolate', 'vegetables', 'milk', 'butter', 'cheese', 'kaya', 'coconut'], true)
                ? 'required_terms' : 'keywords';
            $result['preferences'][$key][] = $keyword;
            $unresolved = preg_replace($pattern, ' ', $unresolved);
        }
        $unresolved = preg_replace('/\b(?:i would like|i want|i would prefer|can i have|could i have|what do you recommend|what would you recommend|what should i eat|what is your|do you have|can you|could you|would you|looking for|for me|per dish|for dinner|for lunch|for breakfast|makan malam|makan tengah hari|sarapan|find|recommendations?|recommend|suggest|suggestions?|please|something|some|anything|good|nice|delicious|tasty|signature|popular|favourite|favorite|best|cadangkan|cadangan|syorkan|makanan|hidangan|sedap|enak|istimewa|tolong|sila|saya|nak|ingin|mahu|makan|sesuatu|yang|untuk|apa|apakah|paling|boleh|bolehkah|anda|a|an|the|food|dishes|dish|and|with|that|which|is|are|me)\b/u', ' ', $unresolved);
        if ($result['excludedAllergens'] !== []) {
            $unresolved = preg_replace('/\b(?:i|have|want|would|like|prefer|looking|to|no|without|avoid|exclude|free|from|and|or|elak|elakkan|tanpa|saya|tidak|tak|boleh|nak)\b/u', ' ', $unresolved) ?? $unresolved;
        }

        $unresolved = preg_replace('/\b(?:menu|menus|senarai|minuman|lauk|set|order|pesan|cari|mau|bagi|berikan|pilihan|famous|lazat|overall|any|apa-apa|sahaja|saja|je|lah|kan|ke|tak|dan|atau|dengan|dalam|bawah|atas|kurang|lebih|daripada|rm|what|on|or|of|for|in|to|at|have|want|would|like|prefer|will|do|you|i|am|my|ada|lagi|ini|itu|please)\b/u', ' ', $unresolved);

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

    /** @return array<int, string> */
    public function culinaryTraits(array $item): array
    {
        $traits = array_filter([$item['flavor_profile'] ?? null]);
        $text = mb_strtolower(($item['name'] ?? '').' '.($item['description'] ?? ''));

        foreach (self::CULINARY_PATTERNS as $trait => $pattern) {
            preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$term, $offset]) {
                $prefix = substr($text, 0, $offset);
                $suffix = substr($text, $offset + strlen($term));
                if (preg_match('/\b(?:no|not|without|tanpa|bebas)(?:\s+[\pL]+){0,2}\s*$/u', $prefix) === 1
                    || preg_match('/^[- ]free\b/u', $suffix) === 1) {
                    continue;
                }

                $traits[] = $trait;
                break;
            }
        }

        return array_values(array_unique($traits));
    }

    /** Hard eligibility never depends on inferred culinary traits except explicit dish terms. */
    public function matchesHardPreferences(array $item, array $preferences): bool
    {
        foreach (['category', 'protein_type', 'dietary_type'] as $field) {
            if (isset($preferences[$field]) && $preferences[$field] !== ''
                && ($item[$field] ?? null) !== $preferences[$field]) {
                return false;
            }
        }

        foreach (['is_available', 'category_active'] as $flag) {
            if (array_key_exists($flag, $item) && ! $item[$flag]) {
                return false;
            }
        }

        if (isset($item['deleted_at'])
            || (isset($preferences['excluded_protein_types'])
                && (! isset($item['protein_type']) || in_array($item['protein_type'], $preferences['excluded_protein_types'], true)))) {
            return false;
        }

        foreach (['max_price', 'min_price'] as $bound) {
            if (! isset($preferences[$bound])) {
                continue;
            }
            if (! is_numeric($preferences[$bound]) || ! is_numeric($item['price'] ?? null)
                || ($bound === 'min_price' && (float) $item['price'] < (float) $preferences[$bound])
                || ($bound === 'max_price' && ((float) $item['price'] > (float) $preferences[$bound]
                    || (($preferences['price_operator'] ?? '<=') === '<' && (float) $item['price'] === (float) $preferences[$bound])))) {
                return false;
            }
        }

        if (($preferences['spice_level'] ?? null) === 'none' && ($item['spice_level'] ?? null) !== 'none') {
            return false;
        }
        if (($preferences['spice_levels'] ?? null) === ['none', 'mild']
            && ! in_array($item['spice_level'] ?? null, ['none', 'mild'], true)) {
            return false;
        }

        return array_diff($preferences['required_terms'] ?? [], $this->culinaryTraits($item)) === [];
    }

    /** Number of independently matched taste, heat and cooking preferences. */
    public function softMatchCount(array $item, array $preferences): int
    {
        $traits = $this->culinaryTraits($item);
        $count = count(array_intersect($preferences['keywords'] ?? [], $traits));
        if (isset($preferences['flavor_profile'])
            && in_array($preferences['flavor_profile'], $traits, true)) {
            $count++;
        }
        if (isset($preferences['spice_level']) && ($item['spice_level'] ?? null) === $preferences['spice_level']) {
            $count++;
        }
        if (isset($preferences['spice_levels']) && in_array($item['spice_level'] ?? null, $preferences['spice_levels'], true)) {
            $count++;
        }

        return $count;
    }

    /** 2: all stored attributes match; 1: culinary flavour match; 0: partial match. */
    public function softMatchLevel(array $item, array $preferences): int
    {
        $requested = count($preferences['keywords'] ?? [])
            + (int) isset($preferences['flavor_profile'])
            + (int) isset($preferences['spice_level'])
            + (int) isset($preferences['spice_levels']);

        if ($this->softMatchCount($item, $preferences) < $requested) {
            return 0;
        }

        if (isset($preferences['flavor_profile']) && ($item['flavor_profile'] ?? null) !== $preferences['flavor_profile']
            && ! $this->containsPhrase((string) ($preferences['preferences_text'] ?? ''), (string) ($item['name'] ?? ''))) {
            return 1;
        }

        return 2;
    }

    /** @param array<string, mixed> $preferences */
    public function filter(Builder $query, array $preferences, bool $includeSoftPreferences = true): Builder
    {
        $fields = $includeSoftPreferences ? array_keys(self::VALUES) : ['protein_type', 'dietary_type'];
        if (! $includeSoftPreferences && ($preferences['spice_level'] ?? null) === 'none') {
            $fields[] = 'spice_level';
        }
        foreach ($fields as $field) {
            if (isset($preferences[$field]) && $preferences[$field] !== '') {
                $query->where($field, $preferences[$field]);
            }
        }

        if (isset($preferences['spice_levels']) && ($includeSoftPreferences || $preferences['spice_levels'] === ['none', 'mild'])) {
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
