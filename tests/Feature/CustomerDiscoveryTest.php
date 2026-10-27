<?php

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\Ai\AiRecommender;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function discoveryXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

test('discovery renders real dish data with accessible images or no media at all', function (string $surface, bool $hasImage) {
    $item = MenuItem::factory()->featured()->create([
        'name' => 'A long menu dish name for a narrow screen',
        'description' => 'The description supplied by the restaurant.',
        'price' => 23.50,
        'spice_level' => 'hot',
        'image_path' => $hasImage ? 'menu/discovery-test.jpg' : null,
    ]);
    config()->set('ai.driver', 'mock');

    $response = match ($surface) {
        'home' => $this->get(route('home')),
        'menu' => $this->get(route('menu.index')),
        'detail' => $this->get(route('menu.show', $item)),
        'rasa' => $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'spicy']),
    };

    $response->assertOk()->assertSee($item->name)->assertSee($item->description)->assertSee('RM 23.50');
    $xpath = discoveryXPath($response->getContent());
    expect($xpath->query('//main//h1')->length)->toBe(1);
    $dishScope = $surface === 'home' ? '//section[@aria-labelledby="featured-heading"]' : '//main';
    expect($xpath->query($dishScope.'//img')->length)->toBe($hasImage ? 1 : 0);
    expect($xpath->query($dishScope.'//figure')->length)->toBe($hasImage ? 1 : 0);

    if ($hasImage) {
        $image = $xpath->query($dishScope.'//img')->item(0);
        expect($image->getAttribute('src'))->toBe(asset('storage/'.$item->image_path))
            ->and($image->getAttribute('alt'))->toBe($item->name)
            ->and($image->getAttribute('loading'))->toBe($surface === 'detail' ? 'eager' : 'lazy')
            ->and($image->hasAttribute('width'))->toBeTrue()
            ->and($image->hasAttribute('height'))->toBeTrue();
        expect($xpath->query('//main//*[@aria-hidden="true"]//img')->length)->toBe(0);
    }

    $response->assertDontSee('🍽️')->assertDontSee('🤔')->assertDontSee('★★★★★');
})->with(['home', 'menu', 'detail', 'rasa'])->with([true, false]);

test('home only presents supplied available featured dishes without invented restaurant claims', function () {
    $featured = MenuItem::factory()->featured()->create();
    $ordinary = MenuItem::factory()->create();
    $unavailable = MenuItem::factory()->featured()->unavailable()->create();

    $this->get(route('home'))->assertOk()
        ->assertSee($featured->name)->assertDontSee($ordinary->name)->assertDontSee($unavailable->name)
        ->assertDontSee('family-run')->assertDontSee('generations')->assertDontSee('Aina R.')
        ->assertDontSee('John T.')->assertDontSee('Mei L.')->assertDontSee("Chef's Selection")
        ->assertSee('href="'.route('reservation.table-food').'"', false)
        ->assertSee('href="'.route('ai.recommendations').'"', false);
});

test('an empty home omits the featured section and retains useful discovery actions', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('id="featured-heading"', false)
        ->assertDontSee('<article', false)->assertSee('Explore the Menu')->assertSee('Reserve Now');
});

test('menu filtering retains database categories attributes query links and pagination', function () {
    $category = MenuCategory::factory()->create(['name' => 'Test category', 'slug' => 'test-category', 'sort_order' => 1]);
    $matchingAttributes = [
        'description' => 'Included filtering fixture description.',
        'price' => 12.50,
        'image_path' => null,
        'spice_level' => 'hot', 'protein_type' => 'seafood',
        'dietary_type' => 'vegetarian', 'flavor_profile' => 'tangy',
    ];
    $matching = MenuItem::factory()->count(10)->for($category, 'category')
        ->sequence(fn (Sequence $sequence): array => [
            'name' => sprintf('Matching dish %02d', $sequence->index + 1),
            'slug' => 'matching-dish-'.($sequence->index + 1),
        ])
        ->create($matchingAttributes);
    $excluded = MenuItem::factory()->for($category, 'category')->create([
        ...$matchingAttributes,
        'name' => 'Matching dish EXCLUDED-SPICE-FIXTURE',
        'slug' => 'excluded-spice-fixture',
        'description' => 'Spice mismatch fixture.',
        'spice_level' => 'mild',
    ]);
    $inactive = MenuCategory::factory()->create([
        'name' => 'Inactive fixture category', 'slug' => 'inactive-fixture-category',
        'is_active' => false, 'sort_order' => 2,
    ]);
    $hidden = MenuItem::factory()->for($inactive, 'category')->create([
        ...$matchingAttributes,
        'name' => 'Matching dish HIDDEN-CATEGORY-FIXTURE',
        'slug' => 'hidden-category-fixture',
        'description' => 'Inactive category fixture.',
    ]);
    $unavailable = MenuItem::factory()->unavailable()->for($category, 'category')->create([
        ...$matchingAttributes,
        'name' => 'Matching dish UNAVAILABLE-FIXTURE',
        'slug' => 'unavailable-fixture',
        'description' => 'Unavailable fixture.',
    ]);
    $query = ['category' => $category->slug, 'q' => 'Matching', 'spice' => 'hot', 'protein' => 'seafood', 'dietary' => 'vegetarian', 'flavor' => 'tangy'];

    $response = $this->get(route('menu.index', $query));
    $response->assertOk()->assertSee($category->name)->assertSee('Matching dish')
        ->assertDontSee($excluded->name)->assertDontSee($hidden->name)->assertDontSee($unavailable->name);
    $xpath = discoveryXPath($response->getContent());
    expect($xpath->query('//main//article')->length)->toBe(9)
        ->and($xpath->query('//nav[@aria-label="Menu categories"]//a[@aria-current="page"]')->item(0)->textContent)->toBe($category->name);
    foreach ($xpath->query('//nav[@aria-label="Menu categories"]//a') as $categoryLink) {
        expect($categoryLink->getAttribute('class'))
            ->toContain('min-h-(--lm-touch)')
            ->toContain('min-w-(--lm-touch)');
    }
    expect($response->viewData('items')->total())->toBe(10);
    $renderedNames = array_map(
        fn (DOMElement $heading): string => trim($heading->textContent),
        iterator_to_array($xpath->query('//main//article//h2')),
    );
    expect($renderedNames)->toBe($matching->take(9)->pluck('name')->all());
    $next = $xpath->query('//nav[@aria-label="Menu pagination"]//a[@rel="next"]')->item(0)->getAttribute('href');
    parse_str(parse_url($next, PHP_URL_QUERY), $nextQuery);
    expect($nextQuery)->toBe([...$query, 'page' => '2']);
    $secondPage = $this->get($next)->assertOk()->assertSee('Page 2 of 2')
        ->assertDontSee($excluded->name)->assertDontSee($hidden->name)->assertDontSee($unavailable->name);
    $secondPageNames = array_map(
        fn (DOMElement $heading): string => trim($heading->textContent),
        iterator_to_array(discoveryXPath($secondPage->getContent())->query('//main//article//h2')),
    );
    expect($secondPageNames)->toBe([$matching->last()->name]);
    $response->assertSee(route('menu.index', array_diff_key($query, ['spice' => true])));
});

test('empty menu and empty Rasa results offer existing recovery destinations', function () {
    $this->get(route('menu.index', ['q' => 'missing']))->assertOk()
        ->assertSee('No dishes match your search')->assertSee('Clear search and filters');
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'a dish not present on this menu'])->assertOk()
        ->assertSee('No dishes match your preferences.')->assertSee('Adjust Preferences')
        ->assertSee('Continue to Full Menu')->assertDontSee('<figure', false);
});

test('menu and detail ordering forms preserve their payload and update only the order cart', function (string $surface) {
    $item = MenuItem::factory()->create();
    $url = $surface === 'detail' ? route('menu.show', $item) : route('menu.index');
    $response = $this->get($url)->assertOk();
    $xpath = discoveryXPath($response->getContent());
    $form = $xpath->query('//main//form[@method="POST"]')->item(0);
    expect($form->getAttribute('action'))->toBe(route('cart.add'))
        ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@name="menu_item_id"]', $form)->item(0)->getAttribute('value'))->toBe((string) $item->id);
    $quantity = $surface === 'detail' ? 3 : 1;
    if ($surface === 'detail') {
        expect($xpath->query('.//select[@name="quantity"]/option', $form)->length)->toBe(10)
            ->and($xpath->query('.//label[@for="quantity"]', $form)->length)->toBe(1);
    } else {
        expect($xpath->query('.//input[@name="quantity"]', $form)->item(0)->getAttribute('value'))->toBe('1');
    }
    $reservationCart = ['items' => ['reserved' => ['quantity' => 7]]];
    $this->withSession(['cart.reservation' => $reservationCart])->from($url)
        ->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => $quantity])
        ->assertRedirect($url)->assertSessionHas('cart.order.items.'.$item->id.'.quantity', $quantity)
        ->assertSessionHas('cart.reservation', $reservationCart);
    $this->get($url)->assertSee($item->name.' added to cart.');
})->with(['menu', 'detail']);

test('unavailable dishes stay inaccessible and the shared presentation disables ordering', function () {
    $item = MenuItem::factory()->unavailable()->create();
    $this->get(route('menu.show', $item))->assertNotFound();
    $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 1])
        ->assertSessionHasErrors('menu_item_id');
    $rendered = $this->blade('<x-menu-item :item="$item" />', ['item' => $item]);
    $rendered->assertSee('Currently unavailable');
    expect(discoveryXPath((string) $rendered)->query('//button[@disabled]')->length)->toBe(1);
});

test('Rasa text and allergen preferences retain values and expose the canonical checkbox group', function () {
    $preferences = ['preferences_text' => 'Chicken under RM20', 'excluded_allergens' => ['peanut', 'milk']];
    $response = $this->withSession(['rasa_ai.preferences' => $preferences])->get(route('ai.recommendations'))->assertOk();
    $xpath = discoveryXPath($response->getContent());
    $form = $xpath->query('//form[@id="rasa-ai-form"]')->item(0);
    $checkboxes = $xpath->query('.//input[@type="checkbox" and @name="excluded_allergens[]"]', $form);
    $renderedCodes = array_map(
        fn (DOMElement $checkbox): string => $checkbox->getAttribute('value'),
        iterator_to_array($checkboxes),
    );
    $canonicalCodes = Allergen::query()->whereIn('code', Allergen::CODES)->orderBy('sort_order')->pluck('code')->all();

    expect($form)->not->toBeNull()
        ->and($xpath->query('.//label[@for="preferences_text"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//textarea[@id="preferences_text" and @name="preferences_text"]', $form)->length)->toBe(1)
        ->and($xpath->evaluate('string(.//textarea[@id="preferences_text"])', $form))->toBe($preferences['preferences_text'])
        ->and($renderedCodes)->toBe($canonicalCodes)
        ->and($renderedCodes)->toHaveCount(10)
        ->and($xpath->query('.//input[@value="peanut" and @checked]', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@value="milk" and @checked]', $form)->length)->toBe(1)
        ->and($xpath->query('.//label[@for="excluded-allergen-peanut"]', $form)->length)->toBe(1)
        ->and($xpath->query('.//label[@for="excluded-allergen-milk"]', $form)->length)->toBe(1);
    expect($xpath->query('//form[@id="rasa-ai-form"]')->length)->toBe(1)
        ->and($xpath->query('//button[@id="rasa-ai-submit"]')->item(0)->getAttribute('aria-busy'))->toBe('false');
});

test('Rasa text and allergen validation errors are associated with their controls', function () {
    $this->from(route('ai.recommendations'))->post(route('ai.recommendations.recommend'), [
        'preferences_text' => str_repeat('x', 301),
        'excluded_allergens' => ['not-a-canonical-allergen'],
    ])->assertSessionHasErrors(['preferences_text', 'excluded_allergens.0']);
    $errorResponse = $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('ai.recommendations'))->assertOk();
    $errors = discoveryXPath($errorResponse->getContent());
    $text = $errors->query('//textarea[@id="preferences_text"]')->item(0);
    $allergenGroup = $errors->query('//fieldset[.//input[@name="excluded_allergens[]"]]')->item(0);
    $allergenCheckbox = $errors->query('//input[@name="excluded_allergens[]"]')->item(0);

    expect($text->getAttribute('aria-invalid'))->toBe('true')
        ->and($text->getAttribute('aria-describedby'))->toBe('preferences_text-help preferences_text-error')
        ->and($errors->query('//*[@id="preferences_text-error"]')->length)->toBe(1)
        ->and($allergenGroup->getAttribute('aria-describedby'))->toBe('excluded_allergens-help excluded_allergens-error')
        ->and($allergenCheckbox->getAttribute('aria-invalid'))->toBe('true')
        ->and($allergenCheckbox->getAttribute('aria-describedby'))->toBe('excluded_allergens-help excluded_allergens-error')
        ->and($errors->query('//*[@id="excluded_allergens-error"]')->length)->toBe(1);
});

test('Rasa reasons reflect actual matching attributes and preserve add to cart forms', function () {
    $item = MenuItem::factory()->create(['spice_level' => 'mild', 'protein_type' => 'seafood', 'dietary_type' => null, 'flavor_profile' => null]);
    $this->mock(AiRecommender::class)->shouldReceive('recommend')->once()->andReturn([$item->id]);
    $response = $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'seafood']);
    $response->assertOk()->assertSee('matches your protein preference')->assertDontSee('matches your spice preference')
        ->assertDontSee('popular dishes')->assertSee('Add to Cart');
    $xpath = discoveryXPath($response->getContent());
    $form = $xpath->query('//main//form')->item(0);
    expect($form->getAttribute('action'))->toBe(route('cart.add'))
        ->and($xpath->query('.//input[@name="quantity"]', $form)->item(0)->getAttribute('value'))->toBe('1')
        ->and($xpath->query('.//input[@name="menu_item_id"]', $form)->item(0)->getAttribute('value'))->toBe((string) $item->id);
});

test('Rasa error state keeps a direct menu escape while Contact has no recommendation form', function () {
    $this->withSession(['error' => 'Rasa AI is temporarily unavailable.'])->get(route('ai.recommendations'))
        ->assertOk()->assertSee('role="alert"', false)->assertSee('Rasa AI is temporarily unavailable.')
        ->assertSee('View the full menu');
    $contact = discoveryXPath($this->get(route('contact.create'))->assertOk()->getContent());
    expect($contact->query('//form[@id="rasa-ai-form"]')->length)->toBe(0)
        ->and($contact->query('//form[@id="contact-form"]//form')->length)->toBe(0);
});

test('adding a recommended dish displays the cart confirmation after returning to Rasa', function () {
    $item = MenuItem::factory()->create(['name' => 'RECOMMENDED-CART-FIXTURE']);
    $this->from(route('ai.recommendations'))->post(route('cart.add'), [
        'menu_item_id' => $item->id, 'quantity' => 1,
    ])->assertRedirect(route('ai.recommendations'));

    $response = $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('ai.recommendations'))->assertOk();

    $response->assertSee('RECOMMENDED-CART-FIXTURE added to cart.')
        ->assertSessionHas('cart.order.items.'.$item->id.'.quantity', 1);
    expect(discoveryXPath($response->getContent())->query('//main//*[@role="status"]')->length)->toBe(1);
});

test('a recommended dish becoming unavailable displays its cart validation error on Rasa', function () {
    $item = MenuItem::factory()->unavailable()->create();
    $this->from(route('ai.recommendations'))->post(route('cart.add'), [
        'menu_item_id' => $item->id, 'quantity' => 1,
    ])->assertSessionHasErrors('menu_item_id');

    $response = $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('ai.recommendations'))->assertOk();

    $response->assertSee('This menu item is no longer available.')->assertSessionMissing('cart.order.items');
    expect(discoveryXPath($response->getContent())->query('//main//*[@role="alert"]')->length)->toBe(1);
});
