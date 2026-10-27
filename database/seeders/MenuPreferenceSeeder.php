<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Services\Ai\AiRecommendationOptions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuPreferenceSeeder extends Seeder
{
    /**
     * Primary protein is not an ingredient/allergen list: none does not mean seafood-free.
     * Dietary claims are omitted when accompaniments or stocks are not described.
     *
     * @var array<string, array{0: string, 1: ?string, 2: ?string, 3: string}>
     */
    public const TAGS = [
        'satay-skewers' => ['medium', 'chicken', null, 'savoury'],
        'ulam-ulaman' => ['medium', 'none', null, 'savoury'],
        'begedil-kentang' => ['none', 'none', null, 'savoury'],
        'roti-canai' => ['mild', 'none', null, 'savoury'],
        'roti-telur' => ['mild', 'egg', null, 'savoury'],
        'nasi-lemak' => ['medium', 'seafood', null, 'savoury'],
        'rendang' => ['hot', 'beef', null, 'savoury'],
        'ayam-masak-lemak-cili-api' => ['hot', 'chicken', null, 'creamy'],
        'ayam-goreng-berempah' => ['none', 'chicken', null, 'savoury'],
        'ikan-kembung-goreng' => ['none', 'seafood', null, 'savoury'],
        'sambal-udang-petai' => ['hot', 'seafood', null, 'savoury'],
        'kangkung-belacan' => ['none', 'none', null, 'savoury'],
        'sayur-lodeh' => ['none', 'none', null, 'creamy'],
        'sambal-telur' => ['medium', 'egg', null, 'savoury'],
        'nasi-goreng' => ['medium', 'seafood', null, 'savoury'],
        'ikan-bakar' => ['medium', 'seafood', null, 'savoury'],
        'ayam-percik' => ['none', 'chicken', null, 'creamy'],
        'sup-ayam' => ['none', 'chicken', null, 'savoury'],
        'sup-daging' => ['none', 'beef', null, 'savoury'],
        'cendol' => ['none', 'none', null, 'sweet'],
        'kuih-lapis' => ['none', 'none', 'vegetarian', 'sweet'],
        'bubur-kacang-hijau' => ['none', 'none', 'vegetarian', 'sweet'],
        'teh-tarik' => ['none', 'none', null, 'sweet'],
        'bandung' => ['none', 'none', null, 'sweet'],
        'kopi-o' => ['none', 'none', null, 'bitter'],
        'sirap-limau' => ['none', 'none', null, 'tangy'],
        'teh-o' => ['none', 'none', null, 'bitter'],
        'spring-rolls' => ['none', 'none', 'vegetarian', 'savoury'],
        'butter-chicken' => ['none', 'chicken', null, 'creamy'],
        'grilled-lamb-chops' => ['none', null, null, 'savoury'],
        'beef-short-ribs' => ['none', 'beef', null, 'sweet'],
        'grilled-salmon' => ['none', 'seafood', null, 'savoury'],
        'tom-yum' => ['hot', 'seafood', null, 'tangy'],
        'sup-buntut' => ['none', 'beef', null, 'savoury'],
        'chocolate-lava-cake' => ['none', 'none', null, 'sweet'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::TAGS as $slug => [$spice, $protein, $dietary, $flavor]) {
                MenuItem::withTrashed()->where('slug', $slug)->update([
                    'spice_level' => $spice,
                    'protein_type' => $protein,
                    'dietary_type' => $dietary,
                    'flavor_profile' => $flavor,
                ]);
            }

            foreach (MenuItem::withTrashed()->get() as $item) {
                $metadata = [];
                foreach (AiRecommendationOptions::VALUES as $field => $values) {
                    $value = $item->$field === null ? null : strtolower(trim($item->$field));
                    $value = match (true) {
                        $field === 'spice_level' && in_array($value, ['not spicy', 'not_spicy'], true) => 'none',
                        $field === 'spice_level' && $value === 'extra hot' => 'hot',
                        $field === 'protein_type' && in_array($value, ['fish', 'prawn', 'shrimp'], true) => 'seafood',
                        $field === 'flavor_profile' && $value === 'savory' => 'savoury',
                        $field === 'flavor_profile' && $value === 'sour' => 'tangy',
                        default => $value,
                    };
                    $metadata[$field] = in_array($value, $values, true)
                        ? $value
                        : ($field === 'spice_level' ? 'none' : null);
                }
                $item->update($metadata);
            }
        });
    }
}
