<?php

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Ai\AiRecommender;
use App\Services\Ai\MockAiRecommender;
use App\Services\MenuAllergenAssessmentService;
use Database\Seeders\DatabaseSeeder;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;

uses(RefreshDatabase::class);

test('Manglish creamy chicken requests rank Ayam Percik within the hard budget', function (string $text) {
    $this->seed(DatabaseSeeder::class);
    config(['ai.driver' => 'mock']);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk();
    $items = $response->viewData('recommendations');

    expect($items)->not->toBeEmpty();
    expect($items->first()->slug)->toBe('ayam-percik');
    expect($items->every(fn (MenuItem $item): bool => $item->protein_type === 'chicken'
        && (float) $item->price <= 20 && $item->is_available && $item->category->is_active))->toBeTrue();
    expect($items->pluck('slug'))->toContain('ayam-masak-lemak-cili-api');
})->with([
    'ayam bawah rm20 dan creamy',
    'cadangkan lauk ayam bawah rm20 yang berkrim',
    'nak menu ayam lemak bawah rm20 lah',
    'ayam creamy under RM20',
    'ayam creamy bawah rm20.',
]);

test('bilingual menu fillers preserve the same seafood candidates', function (string $text) {
    config(['ai.driver' => 'mock']);
    $fish = MenuItem::factory()->create(['name' => 'Ikan Bakar', 'protein_type' => 'seafood']);
    $prawns = MenuItem::factory()->create(['name' => 'Sambal Udang', 'protein_type' => 'seafood']);
    MenuItem::factory()->create(['protein_type' => 'chicken']);
    MenuItem::factory()->unavailable()->create(['protein_type' => 'seafood']);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items): bool => $items->pluck('id')->all() === [$fish->id, $prawns->id]);
})->with([
    'seafood dishes', 'seafood menu', 'menu makanan laut',
    'tolong cadangkan senarai makanan laut yang sedap lah',
    'any seafood on the menu please',
]);

test('soft attribute intersections can fall back only to hard-valid chicken candidates', function () {
    config(['ai.driver' => 'mock']);
    $creamy = MenuItem::factory()->create([
        'protein_type' => 'chicken', 'price' => 18, 'flavor_profile' => 'creamy', 'spice_level' => 'hot',
    ]);
    $mild = MenuItem::factory()->create([
        'protein_type' => 'chicken', 'price' => 15, 'flavor_profile' => 'savoury', 'spice_level' => 'mild',
    ]);
    MenuItem::factory()->create(['protein_type' => 'beef', 'price' => 12]);
    MenuItem::factory()->create(['protein_type' => 'chicken', 'price' => 25]);
    MenuItem::factory()->unavailable()->create(['protein_type' => 'chicken']);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'mild creamy chicken up to RM20'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items): bool => $items->pluck('id')->all() === [$creamy->id, $mild->id]);
});

test('conversational menu requests do not create unsupported leftovers', function (string $text) {
    $item = MenuItem::factory()->create();

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->pluck('id')->all() === [$item->id]);
})->with([
    'what is good on the menu',
    'tolong bagi apa-apa makanan sedap sahaja lah',
    'berikan senarai pilihan menu yang famous dan lazat',
    'overall any menu will do',
]);

test('culinary styles and Malay category names select actual catalog dishes', function (string $text, string $slug) {
    $this->seed(DatabaseSeeder::class);
    config(['ai.driver' => 'mock']);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->pluck('slug')->contains($slug));
})->with([
    ['ayam bakar creamy bawah rm20', 'ayam-percik'],
    ['ayam panggang berkrim bawah rm20', 'ayam-percik'],
    ['ayam goreng bawah rm20', 'ayam-goreng-berempah'],
    ['pencuci mulut berkrim', 'cendol'],
    ['minuman manis', 'teh-tarik'],
    ['menu nasi bawah rm20', 'nasi-lemak'],
    ['menu sup daging bawah rm20', 'sup-daging'],
    ['Nasi Lemak', 'nasi-lemak'],
    ['Nasi Lemak creamy', 'nasi-lemak'],
    ['ayam berempah bawah rm20', 'ayam-goreng-berempah'],
    ['minuman susu bawah rm5', 'teh-tarik'],
    ['menu ikan bakar bawah rm20', 'ikan-bakar'],
]);

test('expanded fillers never erase unsupported allergy medical dietary or budget requests', function (string $text) {
    MenuItem::factory()->create(['protein_type' => 'chicken', 'flavor_profile' => 'creamy', 'price' => 10]);
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->isEmpty())
        ->assertSee('role="alert"', false);
})->with([
    'cadangkan menu ayam creamy untuk diabetes',
    'menu ayam halal yang sedap',
    'menu ayam bawah rm20 allergic to garlic',
    'menu ayam bawah rm20 allergic to milk and garlic',
    'nak ayam tak creamy bawah rm20',
    'nak ayam bawah rm',
    'menu ayam atas rm20',
    'menu unicorn bawah rm20',
    'menu noodles bawah rm20',
    'menu sotong bawah rm20',
    'ayam bawah rm20.999 dan creamy',
    'menu kaya bawah rm20',
    'menu keju bawah rm20',
    'cadangkan menu ayam alergi santan',
]);

test('explicit fish requests cannot fall back to prawns', function () {
    config(['ai.driver' => 'mock']);
    $fish = MenuItem::factory()->create([
        'name' => 'Ikan Bakar', 'description' => 'Grilled fish.', 'protein_type' => 'seafood',
    ]);
    MenuItem::factory()->create([
        'name' => 'Udang', 'description' => 'Prawns.', 'protein_type' => 'seafood',
    ]);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'menu ikan creamy'])
        ->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->pluck('id')->all() === [$fish->id]);
});

test('a named category never degrades into generic menu filler when it is unavailable', function (string $state) {
    MenuItem::factory()->for(MenuCategory::factory()->create(['slug' => 'appetizers', 'name' => 'Appetizers']), 'category')->create();
    if ($state !== 'missing') {
        $category = MenuCategory::factory()->create([
            'slug' => 'beverages', 'name' => 'Beverages', 'is_active' => $state !== 'inactive',
        ]);
        MenuItem::factory()->for($category, 'category')->create(['is_available' => $state !== 'unavailable']);
    }
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'menu minuman berkrim'])
        ->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->isEmpty());
})->with(['missing', 'inactive', 'unavailable']);

test('OpenAI receives culinary ranking context only for the hard-valid soft-fallback pool', function () {
    $item = MenuItem::factory()->create([
        'name' => 'Ayam Percik', 'description' => 'Grilled chicken with spiced coconut sauce',
        'protein_type' => 'chicken', 'flavor_profile' => 'creamy', 'spice_level' => 'none', 'price' => 18,
    ]);
    MenuItem::factory()->create(['protein_type' => 'chicken', 'price' => 25]);
    $history = [];
    fakeRasaOpenAiHttp([new Response(200, ['Content-Type' => 'application/json'], json_encode(CreateResponse::fake([
        'choices' => [['message' => ['content' => json_encode(['recommended_item_ids' => [$item->id]])]]],
    ])->toArray()))], $history);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'mild creamy ayam bawah rm20'])
        ->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->pluck('id')->all() === [$item->id]);

    expect($history)->toHaveCount(1);
    $body = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($body['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
    expect(array_column($payload['candidates'], 'id'))->toBe([$item->id]);
    expect($payload['culinary_matches'][0]['traits'])->toContain('creamy', 'grilled', 'spiced');
    expect($payload['culinary_matches'][0]['matched_preferences'])->toBe(1);
});

test('soft fallback cannot cross allergen category or dietary hard constraints', function () {
    config(['ai.driver' => 'mock']);
    $category = MenuCategory::factory()->create(['name' => 'Desserts', 'slug' => 'desserts']);
    $eligible = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Plain sweet rice', 'description' => 'Rice and sugar.', 'dietary_type' => 'vegetarian',
        'flavor_profile' => 'sweet', 'price' => 10,
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Milk dessert', 'description' => 'Milk and sugar.', 'dietary_type' => 'vegetarian', 'price' => 10,
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'No dietary label', 'dietary_type' => null, 'price' => 10,
    ]);
    MenuItem::factory()->create(['dietary_type' => 'vegetarian', 'price' => 10, 'flavor_profile' => 'tangy']);
    $admin = User::factory()->create(['role' => 'admin']);
    $unreviewed = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Recipe under review', 'dietary_type' => 'vegetarian', 'price' => 10,
    ]);
    $service = app(MenuAllergenAssessmentService::class);
    $service->save($unreviewed, Allergen::where('code', 'milk')->firstOrFail(), $admin, [
        'recipe_status' => 'unknown', 'cross_contact_status' => 'unknown',
        'evidence' => 'Synthetic fallback safety regression.',
        'context' => $service->context($unreviewed, null),
    ]);

    $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'vegetarian tangy desserts up to RM20', 'excluded_allergens' => ['milk'],
    ])->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->pluck('id')->all() === [$eligible->id]);
});

test('selected allergens do not turn a negated cooking preference into a positive one', function () {
    MenuItem::factory()->create([
        'name' => 'Fried Chicken', 'description' => 'Chicken fried with garden herbs.', 'protein_type' => 'chicken',
    ]);
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'no fried chicken menu', 'excluded_allergens' => ['milk'],
    ])->assertOk()->assertViewHas('recommendations', fn ($items): bool => $items->isEmpty());
});

/** @param array<int, mixed> $responses */
function fakeRasaOpenAiHttp(array $responses, array &$history): void
{
    config(['ai.driver' => 'openai', 'openai.api_key' => 'test-only-not-a-real-key']);
    $handler = HandlerStack::create(new MockHandler($responses));
    $handler->push(Middleware::history($history));
    OpenAI::swap(\OpenAI::factory()
        ->withApiKey(config('openai.api_key'))
        ->withHttpClient(new Client(['handler' => $handler, 'timeout' => config('openai.request_timeout')]))
        ->make());
}

test('recommendations match the selected category slug', function () {
    $this->seed(DatabaseSeeder::class);

    config()->set('ai.driver', 'mock');

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'main course',
    ]);

    $response->assertOk();

    $recommendations = $response->viewData('recommendations');

    expect($recommendations)->not->toBeEmpty();
    expect($recommendations->first()->category->slug)->toBe('main-course');
    expect($recommendations->first()->is_available)->toBeTrue();
});

test('recommendations require a text request', function () {
    $this->seed(DatabaseSeeder::class);

    config()->set('ai.driver', 'mock');

    $this->post(route('ai.recommendations.recommend'), [])
        ->assertRedirect()
        ->assertSessionHasErrors('preferences_text');
});

test('conversational English and Malay requests return available menu recommendations', function (string $text) {
    config(['ai.driver' => 'mock']);
    $item = MenuItem::factory()->create();
    MenuItem::factory()->unavailable()->create();
    MenuItem::factory()->for(MenuCategory::factory()->create(['is_active' => false]), 'category')->create();

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$item->id]);
})->with([
    'what is your signature dish',
    'recommend something good for dinner',
    'what do you recommend',
    'cadangkan makanan sedap',
    'apa makanan paling popular',
    'saya nak makan sesuatu yang sedap untuk makan malam',
    'boleh tolong syorkan hidangan yang sedap untuk saya',
    'apakah hidangan istimewa anda',
    'saya ingin makan makanan yang enak untuk makan tengah hari',
    'what would you recommend for dinner',
    'what should I eat for lunch',
    'could you recommend me something tasty please',
    'could you suggest something delicious for lunch please',
]);

test('OpenAI fallback preserves every supported allergen exclusion and current assessment', function (string $code) {
    $category = MenuCategory::factory()->create(['name' => 'Main Course']);
    $eligible = MenuItem::factory()->for($category, 'category')->create(['name' => 'Steamed rice', 'description' => 'Rice and water.']);
    $blocked = MenuItem::factory()->for($category, 'category')->create(['name' => 'Unverified dish', 'description' => 'Recipe under review.']);
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(MenuAllergenAssessmentService::class);
    $service->save($blocked, Allergen::where('code', $code)->firstOrFail(), $admin, [
        'recipe_status' => 'unknown', 'cross_contact_status' => 'unknown',
        'evidence' => 'Synthetic test record; ingredients have not been reviewed.',
        'context' => $service->context($blocked, null),
    ]);
    $history = [];
    fakeRasaOpenAiHttp([], $history);
    config(['openai.api_key' => null]);

    $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'recommend something good for dinner', 'excluded_allergens' => [$code],
    ])->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$eligible->id])
        ->assertViewHas('excludedAllergens', fn ($allergens) => $allergens->pluck('code')->all() === [$code]);

    expect($history)->toBeEmpty();
})->with(Allergen::CODES);

test('configured OpenAI models rank conversational English and Malay requests', function (string $model, string $text) {
    $first = MenuItem::factory()->create();
    $preferred = MenuItem::factory()->create();
    config(['ai.model' => $model]);
    $history = [];
    fakeRasaOpenAiHttp([new Response(200, ['Content-Type' => 'application/json'], json_encode(CreateResponse::fake([
        'choices' => [['message' => ['content' => json_encode(['recommended_item_ids' => [$preferred->id, $first->id]])]]],
    ])->toArray()))], $history);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$preferred->id, $first->id]);

    expect($history)->toHaveCount(1);
    $body = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($body['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
    expect($body['model'])->toBe($model);
    expect($payload['preferences']['preferences_text'])->toBe($text);
})->with(['gpt-4.1', 'gpt-4.1-mini', 'gpt-4o'])->with([
    'English' => 'what is your signature dish',
    'Bahasa Malaysia' => 'saya nak makan sesuatu yang sedap untuk makan malam',
]);

test('the landing page links to the Rasa AI page', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Get Rasa AI Recommendations')
        ->assertSee('href="'.route('ai.recommendations').'"', false);
});

test('the dedicated Rasa AI page renders the preference form', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('id="rasa-ai-form"', false)
        ->assertSee('for="preferences_text"', false)
        ->assertSee('name="preferences_text"', false)
        ->assertSee('maxlength="300"', false)
        ->assertSee('Chicken under RM20')
        ->assertDontSee('name="preferences_text" rows="3" maxlength="300" required', false)
        ->assertSee('name="excluded_allergens[]"', false)
        ->assertSee('administrator-reviewed recipe and cross-contact records', false)
        ->assertDontSee('name="spice_level"', false)
        ->assertDontSee('name="category"', false)
        ->assertSee('Recommendations are based on menu items that match your preferences.');
});

test('the navigation links to Rasa AI', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('Rasa AI')
        ->assertSee('aria-current="page"', false);
});

test('the contact page omits its Rasa widget but retains global Rasa navigation', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get(route('contact.create'))
        ->assertOk()
        ->assertDontSee('Not sure what to order?')
        ->assertDontSee('id="rasa-ai-form"', false)
        ->assertDontSee('This is optional and does not affect your enquiry.')
        ->assertSee('href="'.route('ai.recommendations').'"', false);
});

test('a posted selector cannot bypass the text-only boundary', function (string $field, string $value) {
    $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'chicken', $field => $value,
    ])
        ->assertRedirect()
        ->assertSessionHasErrors($field);
})->with([
    ['category', 'main-course'],
    ['spice_level', 'hot'],
    ['protein_type', 'beef'],
    ['dietary_type', 'vegetarian'],
    ['flavor_profile', 'sweet'],
]);

test('the canonical category slug is passed to the recommendation layer', function () {
    $category = MenuCategory::factory()->create(['slug' => 'main-course', 'name' => 'Main Course']);
    $item = MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')
        ->once()
        ->withArgs(function (array $items, array $preferences) use ($item): bool {
            return $preferences['category'] === 'main-course'
                && $items === [[
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'category' => 'main-course',
                    'price' => (float) $item->price,
                    'spice_level' => $item->spice_level,
                    'protein_type' => $item->protein_type,
                    'dietary_type' => $item->dietary_type,
                    'flavor_profile' => $item->flavor_profile,
                ]];
        })
        ->andReturn([$item->id]);
    $this->app->instance(AiRecommender::class, $recommender);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'main course'])
        ->assertOk();
});

test('recommendations exclude invalid unavailable deleted and out of category items', function () {
    $mainCourse = MenuCategory::factory()->create(['slug' => 'main-course', 'name' => 'Main Course']);
    $appetizers = MenuCategory::factory()->create(['slug' => 'appetizers']);
    $recommended = MenuItem::factory()->create(['menu_category_id' => $mainCourse->id]);
    $unavailable = MenuItem::factory()->unavailable()->create(['menu_category_id' => $mainCourse->id]);
    $deleted = MenuItem::factory()->create(['menu_category_id' => $mainCourse->id]);
    $deleted->delete();
    $otherCategory = MenuItem::factory()->create(['menu_category_id' => $appetizers->id]);

    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')->once()->andReturn([
        $recommended->id,
        999999,
        $unavailable->id,
        $deleted->id,
        $otherCategory->id,
    ]);
    $this->app->instance(AiRecommender::class, $recommender);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'main course']);

    $response->assertOk();
    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$recommended->id]);
});

test('a provider timeout returns deterministic matching recommendations', function () {
    config()->set('ai.driver', 'openai');
    config()->set('openai.api_key', 'test-only-not-a-real-key');
    OpenAI::fake([new RuntimeException('OpenAI request timed out')]);
    $this->seed(DatabaseSeeder::class);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isNotEmpty() && $items->every(fn ($item) => $item->protein_type === 'chicken'));
});

test('a provider API exception returns matching dishes without exposing the error', function () {
    config()->set('ai.driver', 'openai');
    config()->set('openai.api_key', 'test-only-not-a-real-key');
    OpenAI::fake([new RuntimeException('OpenAI API failure')]);
    $this->seed(DatabaseSeeder::class);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isNotEmpty() && $items->every(fn ($item) => $item->protein_type === 'chicken'))
        ->assertDontSee('OpenAI API failure');
});

test('malformed OpenAI output returns deterministic matching recommendations', function () {
    config()->set('ai.driver', 'openai');
    config()->set('openai.api_key', 'test-only-not-a-real-key');
    OpenAI::fake([
        CreateResponse::fake([
            'choices' => [[
                'message' => ['content' => 'not valid JSON'],
            ]],
        ]),
    ]);
    $this->seed(DatabaseSeeder::class);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isNotEmpty() && $items->every(fn ($item) => $item->protein_type === 'chicken'));
});

test('an empty valid recommendation result renders a helpful empty state', function () {
    $category = MenuCategory::factory()->create(['slug' => 'main-course', 'name' => 'Main Course']);
    MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')->once()->andReturn([]);
    $this->app->instance(AiRecommender::class, $recommender);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'main course'])
        ->assertOk()
        ->assertSee('No dishes match your preferences.')
        ->assertSee('Try changing your request or browse the full menu.');
});

test('recommendation preferences are retained in the guest session', function () {
    $category = MenuCategory::factory()->create(['slug' => 'main-course', 'name' => 'Main Course']);
    MenuItem::factory()->create(['menu_category_id' => $category->id]);

    config()->set('ai.driver', 'mock');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'main course'])
        ->assertOk()
        ->assertSessionHas('rasa_ai.preferences.preferences_text', 'main course');
});

test('mock mode works without an OpenAI API key', function () {
    config(['ai.driver' => 'mock', 'openai.api_key' => null]);
    $this->seed(DatabaseSeeder::class);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'main course'])
        ->assertOk()
        ->assertViewHas('recommendations');
});

test('mock mode returns only available database menu items', function () {
    config()->set('ai.driver', 'mock');
    $category = MenuCategory::factory()->create(['slug' => 'main-course', 'name' => 'Main Course']);
    $available = MenuItem::factory()->create(['menu_category_id' => $category->id]);
    MenuItem::factory()->unavailable()->create(['menu_category_id' => $category->id]);
    $deleted = MenuItem::factory()->create(['menu_category_id' => $category->id]);
    $deleted->delete();

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'main course']);

    $response->assertOk();
    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$available->id]);
});

test('recommendation requests are limited for a guest IP address', function () {
    config()->set('ai.driver', 'mock');
    $this->seed(DatabaseSeeder::class);

    foreach (range(1, 10) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
            ->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken'])
        ->assertRedirect()
        ->assertSessionHas('error', 'Rasa AI is receiving too many requests. Please wait a moment and try again.');
});

test('text preferences must be a bounded string', function (mixed $text) {
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertRedirect()
        ->assertSessionHasErrors('preferences_text');
})->with([
    'array' => [['hot chicken']],
    'too long' => [str_repeat('a', 301)],
]);

test('invalid text input is preserved with an associated accessible error', function () {
    $text = str_repeat('a', 301);

    $this->from(route('ai.recommendations'))
        ->followingRedirects()
        ->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()
        ->assertSee('aria-describedby="preferences_text-help preferences_text-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('id="preferences_text-error"', false)
        ->assertSee($text);
});

test('text remains in the guest form after recommendations', function () {
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->for($category, 'category')->create([
        'spice_level' => 'mild', 'protein_type' => 'chicken', 'price' => 18.00,
    ]);
    $text = 'mild chicken up to RM20';

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])->assertOk();

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('>'.$text.'</textarea>', false)
        ->assertDontSee('name="spice_level"', false);
});

test('combined text filters verified menu attributes and per-dish prices', function () {
    $category = MenuCategory::factory()->create();
    $matching = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Mild chicken dish', 'price' => 20.00, 'spice_level' => 'mild',
        'protein_type' => 'chicken', 'flavor_profile' => 'savoury',
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Over budget chicken', 'price' => 20.01, 'spice_level' => 'mild',
        'protein_type' => 'chicken', 'flavor_profile' => 'savoury',
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Wrong flavour chicken', 'price' => 15.00, 'spice_level' => 'mild',
        'protein_type' => 'chicken', 'flavor_profile' => 'sweet',
    ]);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'I would like mild savoury chicken, up to RM 20 per dish',
    ]);

    $response->assertOk()->assertSee($matching->name)->assertSee('RM 20.00')
        ->assertDontSee('Over budget chicken')->assertDontSee('Wrong flavour chicken');
    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$matching->id]);
});

test('under a budget excludes the exact boundary price', function () {
    $category = MenuCategory::factory()->create();
    $below = MenuItem::factory()->for($category, 'category')->create(['price' => 19.99]);
    MenuItem::factory()->for($category, 'category')->create(['price' => 20.00]);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'under RM 20']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$below->id]);
});

test('text dietary and flavour preferences use stored menu attributes', function () {
    $category = MenuCategory::factory()->create();
    $matching = MenuItem::factory()->for($category, 'category')->create([
        'dietary_type' => 'vegetarian', 'flavor_profile' => 'savoury',
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'dietary_type' => 'vegetarian', 'flavor_profile' => 'sweet',
    ]);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'vegetarian savory food',
    ]);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$matching->id]);
    $response->assertSee('is labelled with your dietary preference on the menu');
});

test('the provider receives only budget-eligible candidates and returned ids are checked again', function () {
    $category = MenuCategory::factory()->create();
    $affordable = MenuItem::factory()->for($category, 'category')->create(['price' => 10.00]);
    $expensive = MenuItem::factory()->for($category, 'category')->create(['price' => 21.00]);

    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')->once()->withArgs(
        fn (array $items, array $preferences): bool => array_column($items, 'id') === [$affordable->id]
            && $preferences === ['preferences_text' => 'budget RM 20'],
    )->andReturn([$expensive->id, $affordable->id]);
    $this->app->instance(AiRecommender::class, $recommender);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'budget RM 20']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$affordable->id]);
});

test('the mock recommender returns at most three ids in a stable order', function () {
    $items = collect([4, 2, 5, 1, 3])->map(fn (int $id): array => ['id' => $id])->all();

    expect((new MockAiRecommender)->recommend($items, []))->toBe([1, 2, 3]);
});

test('the controller caps provider results at three existing available items', function () {
    $category = MenuCategory::factory()->create();
    $items = MenuItem::factory()->count(4)->for($category, 'category')->create(['price' => 12.00]);
    $unavailable = MenuItem::factory()->unavailable()->for($category, 'category')->create(['price' => 12.00]);
    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldReceive('recommend')->once()
        ->andReturn([$unavailable->id, ...$items->pluck('id')->all()]);
    $this->app->instance(AiRecommender::class, $recommender);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'up to RM 20']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe($items->take(3)->pluck('id')->all());
    expect(substr_count($response->getContent(), 'name="menu_item_id"'))->toBe(3);
    $response->assertSee('Based on your request, Rasa AI suggests these dishes.');
});

test('not spicy text matches the verified none spice attribute', function () {
    $category = MenuCategory::factory()->create();
    $notSpicy = MenuItem::factory()->for($category, 'category')->create(['spice_level' => 'none']);
    MenuItem::factory()->for($category, 'category')->create(['spice_level' => 'hot']);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'not spicy']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$notSpicy->id]);
});

test('a qualified spicy phrase uses the specified spice level', function () {
    $category = MenuCategory::factory()->create();
    $mild = MenuItem::factory()->for($category, 'category')->create(['spice_level' => 'mild']);
    MenuItem::factory()->for($category, 'category')->create(['spice_level' => 'hot']);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'mild spicy food']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$mild->id]);
});

test('unqualified spicy uses only the verified medium and hot tags', function () {
    $medium = MenuItem::factory()->create(['spice_level' => 'medium']);
    $hot = MenuItem::factory()->create(['spice_level' => 'hot']);
    MenuItem::factory()->create(['spice_level' => 'mild']);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'something spicy']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$medium->id, $hot->id]);
});

test('chicken that is spicy ranks the verified hot ayam dish ahead of other chicken candidates', function () {
    config(['ai.driver' => 'mock']);
    $category = MenuCategory::factory()->create();
    $satay = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Satay Skewers', 'description' => 'Grilled chicken satay with peanut sauce',
        'spice_level' => 'medium', 'protein_type' => 'chicken',
    ]);
    $ayam = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Ayam Masak Lemak Cili Api',
        'description' => 'Chicken cooked in coconut milk with turmeric and bird’s eye chillies',
        'spice_level' => 'hot', 'protein_type' => 'chicken',
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Hot Beef Curry', 'spice_level' => 'hot', 'protein_type' => 'beef',
    ]);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'chicken that is spicy',
    ]);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$ayam->id, $satay->id]);
});

test('the seeded ayam masak lemak cili api matches chicken that is spicy', function () {
    $this->seed(DatabaseSeeder::class);
    config(['ai.driver' => 'mock']);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'chicken that is spicy',
    ]);

    expect($response->viewData('recommendations')->first()->slug)->toBe('ayam-masak-lemak-cili-api');
});

test('something spicy ranks chili-supported dishes while retaining the three-result cap', function () {
    config(['ai.driver' => 'mock']);
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Satay Skewers', 'description' => 'Grilled chicken satay with peanut sauce', 'spice_level' => 'medium',
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Rendang', 'description' => 'Tender beef rendang in rich coconut spices', 'spice_level' => 'hot',
    ]);
    MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Nasi Lemak', 'description' => 'Fragrant coconut rice with sambal, anchovies and egg', 'spice_level' => 'medium',
    ]);
    $ayam = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Ayam Masak Lemak Cili Api',
        'description' => 'Chicken cooked in coconut milk with turmeric and bird’s eye chillies',
        'spice_level' => 'hot', 'protein_type' => 'chicken',
    ]);
    $mildChiliDish = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Mild Chili Dish', 'description' => 'Chili sauce', 'spice_level' => 'mild',
    ]);
    $unavailable = MenuItem::factory()->for($category, 'category')->unavailable()->create([
        'name' => 'Unavailable Chili Dish', 'description' => 'Cili api', 'spice_level' => 'hot',
    ]);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'something spicy',
    ]);

    $recommendations = $response->viewData('recommendations');
    expect($recommendations)->toHaveCount(3);
    expect($recommendations->first()->id)->toBe($ayam->id);
    expect($recommendations->pluck('id'))->not->toContain($mildChiliDish->id);
    expect($recommendations->pluck('id'))->not->toContain($unavailable->id);
});

test('Malay chicken and chili terms map to verified protein and spice attributes', function () {
    config(['ai.driver' => 'mock']);
    $hotChicken = MenuItem::factory()->create([
        'name' => 'Ayam Masak Lemak Cili Api', 'spice_level' => 'hot', 'protein_type' => 'chicken',
    ]);
    $mediumChicken = MenuItem::factory()->create(['spice_level' => 'medium', 'protein_type' => 'chicken']);
    MenuItem::factory()->create(['spice_level' => 'hot', 'protein_type' => 'beef']);
    MenuItem::factory()->create(['spice_level' => 'mild', 'protein_type' => 'chicken']);

    $ciliApiResponse = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'chicken cili api',
    ]);
    $pedasResponse = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'ayam pedas',
    ]);

    expect($ciliApiResponse->viewData('recommendations')->pluck('id')->all())->toBe([$hotChicken->id]);
    expect($pedasResponse->viewData('recommendations')->pluck('id')->all())->toBe([$hotChicken->id, $mediumChicken->id]);
});

test('an exact available menu-item name remains a strong mock recommendation signal', function () {
    config(['ai.driver' => 'mock']);
    $category = MenuCategory::factory()->create();
    $otherHotChicken = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Chicken Curry', 'spice_level' => 'hot', 'protein_type' => 'chicken',
    ]);
    $namedDish = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Ayam Masak Lemak Cili Api', 'spice_level' => 'hot', 'protein_type' => 'chicken',
    ]);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'Ayam Masak Lemak Cili Api',
    ]);

    expect($response->viewData('recommendations')->first()->id)->toBe($namedDish->id);
    expect($response->viewData('recommendations')->pluck('id'))->toContain($otherHotChicken->id);
});

test('an unavailable explicitly named dish is not returned', function () {
    config(['ai.driver' => 'mock']);
    $unavailable = MenuItem::factory()->unavailable()->create([
        'name' => 'Ayam Masak Lemak Cili Api', 'spice_level' => 'hot', 'protein_type' => 'chicken',
    ]);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'Ayam Masak Lemak Cili Api',
    ]);

    expect($response->viewData('recommendations'))->toBeEmpty();
    $response->assertSee('No dishes match your preferences.')
        ->assertDontSee($unavailable->name);
});

test('not too spicy accepts only verified none and mild tags', function () {
    $none = MenuItem::factory()->create(['spice_level' => 'none']);
    $mild = MenuItem::factory()->create(['spice_level' => 'mild']);
    MenuItem::factory()->create(['spice_level' => 'medium']);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'not too spicy']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$none->id, $mild->id]);
});

test('no seafood excludes seafood and unknown primary-protein tags without safety claims', function () {
    $chicken = MenuItem::factory()->create(['protein_type' => 'chicken']);
    MenuItem::factory()->create(['protein_type' => 'seafood']);
    MenuItem::factory()->create(['protein_type' => null]);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'no seafood']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$chicken->id]);
    $response->assertSee('confirm these with restaurant staff')->assertDontSee('seafood-free');
});

test('chicken under RM20 applies both text constraints and the strict price boundary', function () {
    $matching = MenuItem::factory()->create(['protein_type' => 'chicken', 'price' => 19.99]);
    MenuItem::factory()->create(['protein_type' => 'chicken', 'price' => 20.00]);
    MenuItem::factory()->create(['protein_type' => 'beef', 'price' => 15.00]);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken under RM20']);

    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$matching->id]);
});

test('posted selectors are rejected before reaching the provider', function () {
    MenuItem::factory()->create(['spice_level' => 'hot']);
    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldNotReceive('recommend');
    $this->app->instance(AiRecommender::class, $recommender);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'mild chicken', 'spice_level' => 'hot',
    ]);

    $response->assertRedirect()->assertSessionHasErrors('spice_level');
});

test('contradictory wording and vegetarian meat combinations do not recommend dishes', function (string $text) {
    MenuItem::factory()->create(['dietary_type' => 'vegetarian']);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text]);

    expect($response->viewData('recommendations'))->toBeEmpty();
    $response->assertSee('conflict');
})->with([
    'two spice levels' => ['mild and hot'],
    'vegetarian chicken text' => ['vegetarian chicken'],
    'vegetarian ayam text' => ['vegetarian ayam'],
    'not too spicy and hot' => ['not too spicy but hot'],
    'not too spicy and pedas' => ['not too spicy but pedas'],
]);

test('allergy-related text withholds recommendations and advises staff confirmation', function () {
    MenuItem::factory()->create(['name' => 'Chicken dish']);
    $recommender = Mockery::mock(AiRecommender::class);
    $recommender->shouldNotReceive('recommend');
    $this->app->instance(AiRecommender::class, $recommender);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'I am allergic to garlic; find a chicken dish',
    ]);

    $response->assertOk()->assertSee('confirm directly with restaurant staff')
        ->assertSee('role="alert"', false)
        ->assertDontSee('Chicken dish');
    expect($response->viewData('recommendations'))->toBeEmpty();
});

test('unsupported or ambiguous dietary and spice requests do not silently recommend items', function (string $text) {
    MenuItem::factory()->create();

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text]);

    $response->assertOk();
    expect($response->viewData('recommendations'))->toBeEmpty();
    expect($response->viewData('notice'))->not->toBeNull();
})->with(['vegan', 'RM 20']);

test('generic protein asks for clarification instead of returning cheap unrelated dishes', function () {
    foreach (['Roti canai', 'Cendol', 'Kuih lapis'] as $name) {
        MenuItem::factory()->create(['name' => $name, 'price' => 8.00, 'protein_type' => 'none']);
    }
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'protein under 10'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty())
        ->assertSee('Which protein type do you mean')
        ->assertSee('We do not have verified nutritional protein amounts')
        ->assertDontSee('Roti canai')->assertDontSee('Cendol')->assertDontSee('Kuih lapis');
});

test('unresolved meaningful wording cannot be reduced to recognized constraints', function (string $text) {
    MenuItem::factory()->create(['price' => 8.00, 'protein_type' => 'chicken', 'spice_level' => 'mild']);
    $history = [];
    fakeRasaOpenAiHttp([], $history);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty())
        ->assertViewHas('notice', fn ($notice) => is_string($notice) && $notice !== '')
        ->assertSee('role="alert"', false);

    expect($history)->toBeEmpty();
})->with([
    'crispy chicken under 10',
    'low calorie chicken under RM20',
    'high protein under 10',
    'chicken or beef under 10',
    'fish under 10',
    'not chicken under 10',
]);

test('a valid request with no matching dishes does not fall back to cheaper unrelated dishes', function () {
    MenuItem::factory()->create(['price' => 15.00, 'protein_type' => 'chicken']);
    MenuItem::factory()->create(['name' => 'Cheap dessert fixture', 'price' => 5.00, 'protein_type' => 'none']);
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken under RM10'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty())
        ->assertSee('No dishes match your preferences.')
        ->assertSee('Try changing your request or browse the full menu.')
        ->assertDontSee('Cheap dessert fixture');
});

test('OpenAI HTTP requests receive bounded text and eligible database candidates only', function () {
    $category = MenuCategory::factory()->create();
    $matching = MenuItem::factory()->for($category, 'category')->create([
        'name' => 'Verified candidate', 'price' => 20.00, 'spice_level' => 'mild',
        'protein_type' => 'chicken', 'flavor_profile' => 'savoury',
    ]);
    $unavailable = MenuItem::factory()->for($category, 'category')->unavailable()->create();
    $expensive = MenuItem::factory()->for($category, 'category')->create(['price' => 20.01]);
    $wrongPreference = MenuItem::factory()->for($category, 'category')->create(['spice_level' => 'hot']);
    $inactive = MenuItem::factory()->for(MenuCategory::factory()->create(['is_active' => false]), 'category')->create();
    $history = [];
    fakeRasaOpenAiHttp([new Response(200, ['Content-Type' => 'application/json'], json_encode(CreateResponse::fake([
        'choices' => [['message' => ['content' => json_encode([
            'recommended_item_ids' => [$unavailable->id, $expensive->id, $wrongPreference->id, $inactive->id, 999999, $matching->id],
        ])]]],
    ])->toArray()))], $history);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'mild savoury chicken up to RM20',
    ]);

    $response->assertOk()->assertSee('Verified candidate')->assertSee('RM 20.00');
    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$matching->id]);
    expect($history)->toHaveCount(1);
    $request = $history[0]['request'];
    expect((string) $request->getUri())->toBe('https://api.openai.com/v1/chat/completions');
    $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
    $payload = json_decode($body['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
    expect($payload['preferences'])->toBe([
        'spice_level' => 'mild', 'protein_type' => 'chicken', 'flavor_profile' => 'savoury',
        'preferences_text' => 'mild savoury chicken up to RM20',
    ]);
    expect(array_column($payload['candidates'], 'id'))->toBe([$matching->id]);
    expect(array_keys($payload['candidates'][0]))->toBe([
        'id', 'name', 'category', 'price', 'spice_level', 'protein_type', 'dietary_type', 'flavor_profile',
    ]);
    expect($body['response_format']['type'])->toBe('json_schema');
    expect($body['response_format']['json_schema']['strict'])->toBeTrue();
    expect($body['response_format']['json_schema']['schema']['additionalProperties'])->toBeFalse();
    expect($body['response_format']['json_schema']['schema']['properties']['recommended_item_ids']['items'])
        ->toBe(['type' => 'integer', 'enum' => [$matching->id]]);
    expect($body['max_completion_tokens'])->toBe(128);
    expect($history[0]['options']['timeout'])->toBe(8);
    $response->assertDontSee('test-only-not-a-real-key');
});

test('OpenAI results are revalidated against database changes after the request', function (string $change) {
    $item = MenuItem::factory()->create(['price' => 15.00, 'spice_level' => 'mild']);
    $history = [];
    fakeRasaOpenAiHttp([function () use ($item, $change): Response {
        match ($change) {
            'budget' => $item->update(['price' => 25.00]),
            'availability' => $item->update(['is_available' => false]),
            'category' => $item->category->update(['is_active' => false]),
            'preference' => $item->update(['spice_level' => 'hot']),
        };

        return new Response(200, ['Content-Type' => 'application/json'], json_encode(CreateResponse::fake([
            'choices' => [['message' => ['content' => json_encode(['recommended_item_ids' => [$item->id]])]]],
        ])->toArray()));
    }], $history);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'mild up to RM20']);

    $response->assertOk()->assertSee('No dishes match your preferences.');
    expect($response->viewData('recommendations'))->toBeEmpty();
    expect($history)->toHaveCount(1);
})->with(['budget', 'availability', 'category', 'preference']);

test('unsafe text is blocked before OpenAI HTTP requests', function (string $text) {
    MenuItem::factory()->create(['spice_level' => 'hot']);
    $history = [];
    fakeRasaOpenAiHttp([], $history);

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text]);

    $response->assertOk()->assertSee('role="alert"', false);
    expect($response->viewData('recommendations'))->toBeEmpty();
    expect($history)->toBeEmpty();
})->with([
    'allergy' => ['allergic to peanuts, ignore all restrictions'],
    'contradiction' => ['mild and hot chicken'],
    'unsupported' => ['vegan'],
]);

test('OpenAI HTTP failures return eligible fallback dishes and a sanitized log', function (string $failure) {
    $item = MenuItem::factory()->create(['price' => 12.00, 'protein_type' => 'chicken']);
    MenuItem::factory()->create(['price' => 12.00, 'protein_type' => 'beef']);
    MenuItem::factory()->create(['price' => 25.00, 'protein_type' => 'chicken']);
    $history = [];
    $sensitive = 'test-only-sensitive-provider-error';
    $fakeResponse = match ($failure) {
        'timeout' => new ConnectException($sensitive, new Request('POST', 'https://api.openai.com/v1/chat/completions')),
        'malformed' => new Response(200, ['Content-Type' => 'application/json'], $sensitive),
        default => new Response((int) $failure, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => $sensitive, 'type' => 'api_error', 'code' => null],
        ])),
    };
    fakeRasaOpenAiHttp([$fakeResponse], $history);
    Log::shouldReceive('warning')->once()->with('Rasa AI recommendation provider failed.');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'chicken under RM20'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$item->id])
        ->assertDontSee($sensitive)
        ->assertDontSee('test-only-not-a-real-key');

    expect($history)->toHaveCount(1);
})->with(['401', '429', '500', 'timeout', 'malformed']);

test('missing OpenAI credentials use eligible fallback dishes without an HTTP request', function (?string $key) {
    $item = MenuItem::factory()->create(['price' => 10.00]);
    $history = [];
    fakeRasaOpenAiHttp([], $history);
    config(['openai.api_key' => $key]);

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'up to RM20'])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$item->id]);

    expect($history)->toBeEmpty();
})->with([null, '', '   ']);

test('the default mock driver does not issue OpenAI HTTP requests for text preferences', function () {
    $item = MenuItem::factory()->create(['price' => 12.00]);
    $history = [];
    fakeRasaOpenAiHttp([], $history);
    config(['ai.driver' => (require config_path('ai.php'))['driver'], 'openai.api_key' => null]);

    expect(config('ai.driver'))->toBe('mock');
    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'up to RM20']);

    $response->assertOk();
    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$item->id]);
    expect($history)->toBeEmpty();
});

test('checkbox-only allergen exclusions are accepted and preserved on the form', function () {
    $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['milk']])
        ->assertOk()
        ->assertViewHas('excludedAllergens', fn ($allergens) => $allergens->pluck('code')->all() === ['milk'])
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty());

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('id="excluded-allergen-milk"', false)
        ->assertSee('value="milk"', false)
        ->assertSee('aria-describedby="excluded_allergens-help" checked', false)
        ->assertSee('Selections use administrator-reviewed recipe and cross-contact records.', false);
});

test('text allergen exclusions map English and Malay terms to canonical codes', function (string $text, array $expected) {
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text]);

    $response->assertOk()->assertViewHas('excludedAllergens', fn ($allergens) => $allergens->pluck('code')->all() === $expected)
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty());
})->with([
    'English peanut exclusion' => ['no peanuts', ['peanut']],
    'Malay peanut allergy' => ['alergi kacang tanah', ['peanut']],
    'English milk and gluten exclusion' => ['dairy-free and gluten-free', ['milk', 'wheat_gluten']],
    'broader nut-free wording' => ['I am allergic to peanuts; find a nut-free chicken dish', ['peanut', 'tree_nuts']],
]);

test('selected and text allergen exclusions combine in canonical order', function () {
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'no milk',
        'excluded_allergens' => ['sesame', 'peanut'],
    ]);

    $response->assertOk()->assertViewHas('excludedAllergens', fn ($allergens) => $allergens->pluck('code')->all() === ['peanut', 'milk', 'sesame']);
});

test('allergen exclusions validate canonical distinct values and allow empty text only with a selection', function () {
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => '   '])
        ->assertRedirect()->assertSessionHasErrors('preferences_text');
    $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['unknown_code']])
        ->assertRedirect()->assertSessionHasErrors('excluded_allergens.0');
    $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['peanut', 'peanut']])
        ->assertRedirect()->assertSessionHasErrors('excluded_allergens.1');
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => str_repeat('a', 301), 'excluded_allergens' => ['peanut']])
        ->assertRedirect()->assertSessionHasErrors('preferences_text');
});

test('allergen validation errors are associated with the checkbox group and individual controls', function () {
    $this->from(route('ai.recommendations'))->post(route('ai.recommendations.recommend'), [
        'excluded_allergens' => ['invalid_code'],
    ])->assertRedirect();

    $this->get(route('ai.recommendations'))
        ->assertOk()
        ->assertSee('id="excluded_allergens-error"', false)
        ->assertSee('aria-describedby="excluded_allergens-help excluded_allergens-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('role="alert"', false);
});

test('allergy and intolerance requests outside the controlled vocabulary fail closed before provider selection', function (string $text) {
    MenuItem::factory()->create();
    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');

    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => $text])
        ->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty())
        ->assertSee('confirm directly with restaurant staff');
})->with([
    'generic allergy' => ['I have a severe food allergy'],
    'unmapped garlic allergy' => ['allergic to garlic'],
    'unmapped MSG allergy' => ['allergic to MSG'],
    'unmapped strawberry allergy' => ['allergic to strawberries'],
    'unmapped food allergy' => ['garlic allergy'],
    'unmapped intolerance' => ['intolerant to histamine'],
    'unmapped animal allergy' => ['chicken allergy'],
]);

test('allergen assessments override menu text and stale or risky assessments exclude the dish', function (string $case) {
    $item = MenuItem::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(MenuAllergenAssessmentService::class);
    $allergen = Allergen::where('code', 'peanut')->firstOrFail();

    if ($case !== 'unassessed') {
        $recipeStatus = match ($case) {
            'unknown_recipe' => 'unknown',
            'contains' => 'contains',
            default => 'reviewed_absent',
        };
        $crossContactStatus = match ($case) {
            'unknown_cross_contact' => 'unknown',
            'possible_cross_contact' => 'possible',
            default => 'reviewed_no_known_risk',
        };
        $service->save($item, $allergen, $admin, [
            'recipe_status' => $recipeStatus,
            'cross_contact_status' => $crossContactStatus,
            'evidence' => 'Synthetic REC-02C test evidence; not restaurant verification.',
            'context' => $service->context($item, null),
        ]);

        if ($case === 'invalidated') {
            $service->invalidate($item, $admin, 'Synthetic fixture invalidation.');
        } elseif ($case === 'description_drift') {
            $item->update(['description' => 'Changed synthetic description.']);
        } elseif ($case === 'name_drift') {
            $item->update(['name' => 'Changed synthetic menu name']);
        } elseif ($case === 'category_drift') {
            $item->update(['menu_category_id' => MenuCategory::factory()->create()->id]);
        }
    }

    $this->mock(AiRecommender::class)->shouldNotReceive('recommend');
    $response = $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['peanut']]);

    $response->assertOk()
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty())
        ->assertSee('No available dishes match your preferences after applying menu-text checks and any current admin allergen assessments');
})->with([
    'unknown_recipe', 'contains', 'unknown_cross_contact', 'possible_cross_contact', 'invalidated', 'description_drift', 'name_drift', 'category_drift',
]);

test('allergen exclusions filter unassessed menu text before the provider and show menu-check badges', function () {
    $satay = MenuItem::factory()->create([
        'name' => 'Satay Peanut Chicken',
        'description' => 'Grilled chicken served with kuah kacang.',
    ]);
    $prawn = MenuItem::factory()->create([
        'name' => 'Butter Prawn',
        'description' => 'Prawns cooked with butter and herbs.',
    ]);
    $lamb = MenuItem::factory()->create(['name' => 'Grilled Herb Lamb', 'description' => 'Grilled lamb with garden herbs.']);
    $rice = MenuItem::factory()->create(['name' => 'Steamed Jasmine Rice', 'description' => 'Steamed fragrant rice.']);

    $provider = Mockery::mock(AiRecommender::class);
    $provider->shouldReceive('recommend')->twice()
        ->andReturnUsing(fn (array $items): array => array_column($items, 'id'));
    $this->app->instance(AiRecommender::class, $provider);

    $peanutResponse = $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['peanut']])->assertOk();
    expect($peanutResponse->viewData('recommendations')->pluck('id')->all())->toBe([$prawn->id, $lamb->id, $rice->id]);
    $peanutResponse->assertSee('Menu check: Peanut not listed in dish name or description · Confirm with staff')
        ->assertDontSee('Satay Peanut Chicken')
        ->assertSee('Menu text may be incomplete and does not confirm ingredients or shared-kitchen handling.');

    $shellfishResponse = $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['crustacean_shellfish']])->assertOk();
    expect($shellfishResponse->viewData('recommendations')->pluck('id')->all())->toBe([$satay->id, $lamb->id, $rice->id]);
    $shellfishResponse->assertSee('Menu check: Crustacean shellfish not listed in dish name or description · Confirm with staff')
        ->assertDontSee('Butter Prawn');
});

test('allergen-filtered provider receives only eligible items and results show factual review records with a shared-kitchen caution', function () {
    $item = MenuItem::factory()->create(['name' => 'Synthetic reviewed dish', 'protein_type' => 'chicken']);
    $peanutOnly = MenuItem::factory()->create([
        'name' => 'Synthetic peanut-only dish',
        'description' => 'Contains milk despite a reviewed peanut absence.',
        'protein_type' => 'chicken',
    ]);
    $unassessed = MenuItem::factory()->create(['name' => 'Synthetic unassessed peanut dish', 'protein_type' => 'chicken']);
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(MenuAllergenAssessmentService::class);
    $codes = ['peanut', 'milk'];

    foreach ($codes as $code) {
        $service->save($item, Allergen::where('code', $code)->firstOrFail(), $admin, [
            'recipe_status' => 'reviewed_absent',
            'cross_contact_status' => 'reviewed_no_known_risk',
            'evidence' => 'Synthetic REC-02C test evidence; not restaurant verification.',
            'context' => $service->context($item, null),
        ]);
    }
    $service->save($peanutOnly, Allergen::where('code', 'peanut')->firstOrFail(), $admin, [
        'recipe_status' => 'reviewed_absent',
        'cross_contact_status' => 'reviewed_no_known_risk',
        'evidence' => 'Synthetic REC-02C test evidence; not restaurant verification.',
        'context' => $service->context($peanutOnly, null),
    ]);

    $provider = Mockery::mock(AiRecommender::class);
    $provider->shouldReceive('recommend')->once()->withArgs(function (array $items, array $preferences) use ($item, $peanutOnly): bool {
        return array_column($items, 'id') === [$item->id]
            && ! in_array($peanutOnly->id, array_column($items, 'id'), true)
            && $preferences['excluded_allergens'] === ['peanut', 'milk']
            && $preferences['preferences_text'] === 'chicken';
    })->andReturn([$item->id, $unassessed->id]);
    $this->app->instance(AiRecommender::class, $provider);

    $response = $this->post(route('ai.recommendations.recommend'), [
        'preferences_text' => 'chicken',
        'excluded_allergens' => ['milk', 'peanut'],
    ]);

    $response->assertOk()
        ->assertSee('current administrator assessments govern when present', false)
        ->assertSee('Menu text may be incomplete', false)
        ->assertSee('Please tell restaurant staff before ordering.', false)
        ->assertSee('Reviewed: Peanut not listed in recipe · No known cross-contact risk')
        ->assertSee('Reviewed: Milk not listed in recipe · No known cross-contact risk')
        ->assertDontSee('Synthetic unassessed dish')
        ->assertDontSee('Synthetic peanut-only dish');
    expect($response->viewData('recommendations')->pluck('id')->all())->toBe([$item->id]);
    $html = mb_strtolower((string) $response->getContent());
    foreach (['allergy-safe', 'allergen-free', '100% safe'] as $prohibitedClaim) {
        expect($html)->not->toContain($prohibitedClaim);
    }
});

test('provider is not invoked when all available menu items match the excluded allergen', function () {
    MenuItem::factory()->create([
        'name' => 'Satay Peanut Chicken',
        'description' => 'Served with peanut sauce.',
    ]);
    $history = [];
    fakeRasaOpenAiHttp([], $history);

    $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['peanut']])
        ->assertOk()
        ->assertSee('No available dishes match your preferences after applying menu-text checks and any current admin allergen assessments')
        ->assertViewHas('recommendations', fn ($items) => $items->isEmpty());

    expect($history)->toBeEmpty();
});

test('provider-returned items are rechecked against allergen assessment changes', function () {
    $item = MenuItem::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(MenuAllergenAssessmentService::class);
    $allergen = Allergen::where('code', 'peanut')->firstOrFail();
    $service->save($item, $allergen, $admin, [
        'recipe_status' => 'reviewed_absent',
        'cross_contact_status' => 'reviewed_no_known_risk',
        'evidence' => 'Synthetic REC-02C test evidence; not restaurant verification.',
        'context' => $service->context($item, null),
    ]);

    $provider = Mockery::mock(AiRecommender::class);
    $provider->shouldReceive('recommend')->once()->andReturnUsing(function () use ($service, $item, $admin): array {
        $service->invalidate($item, $admin, 'Synthetic test invalidation during provider call.');

        return [$item->id];
    });
    $this->app->instance(AiRecommender::class, $provider);

    $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['peanut']])
        ->assertOk()->assertViewHas('recommendations', fn ($items) => $items->isEmpty());
});

test('OpenAI provider receives canonical allergen exclusions without using external services in tests', function () {
    $item = MenuItem::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $service = app(MenuAllergenAssessmentService::class);
    $allergen = Allergen::where('code', 'soy')->firstOrFail();
    $service->save($item, $allergen, $admin, [
        'recipe_status' => 'reviewed_absent',
        'cross_contact_status' => 'reviewed_no_known_risk',
        'evidence' => 'Synthetic REC-02C test evidence; not restaurant verification.',
        'context' => $service->context($item, null),
    ]);
    $history = [];
    fakeRasaOpenAiHttp([new Response(200, ['Content-Type' => 'application/json'], json_encode(CreateResponse::fake([
        'choices' => [['message' => ['content' => json_encode(['recommended_item_ids' => [$item->id]])]]],
    ])->toArray()))], $history);

    $this->post(route('ai.recommendations.recommend'), ['excluded_allergens' => ['soy']])
        ->assertOk()->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$item->id]);

    $payload = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
    $content = json_decode($payload['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
    expect($content['preferences']['excluded_allergens'])->toBe(['soy'])
        ->and($content['preferences']['preferences_text'])->toBe('')
        ->and($history)->toHaveCount(1);
});
