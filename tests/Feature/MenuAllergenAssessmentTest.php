<?php

use App\Models\Allergen;
use App\Models\MenuItem;
use App\Models\MenuItemAllergenAssessment;
use App\Models\User;
use App\Services\MenuAllergenAssessmentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->item = MenuItem::factory()->create();
    $this->allergen = Allergen::where('code', 'peanut')->firstOrFail();
    $this->service = app(MenuAllergenAssessmentService::class);
    $this->payload = [
        'recipe_status' => 'contains', 'cross_contact_status' => 'unknown',
        'evidence' => 'Isolated test-only restaurant document reference A1.',
        'documented_information' => '1', 'context' => $this->service->context($this->item, null),
    ];
    $this->endpoint = route('admin.menu.allergens.update', [$this->item, $this->allergen]);
});

test('controlled vocabulary is present and existing items have no inferred assessments', function () {
    expect(Allergen::orderBy('sort_order')->pluck('code')->all())->toBe([
        'peanut', 'tree_nuts', 'milk', 'egg', 'soy', 'wheat_gluten', 'fish', 'crustacean_shellfish', 'molluscs', 'sesame',
    ]);
    $this->assertDatabaseCount('menu_item_allergen_assessments', 0);
    $this->actingAs($this->admin)->get(route('admin.menu.allergens.index', $this->item))
        ->assertOk()->assertSee('Unknown')->assertSee('Not reviewed')->assertDontSee('Reviewed absent');
    $this->get(route('admin.menu.items.edit', $this->item))->assertSee('Manage allergen assessments');
});

test('menu text is used only when an item has no allergen assessment row', function () {
    $this->item->update([
        'name' => 'Satay Peanut Chicken',
        'description' => 'Grilled chicken served with kuah kacang.',
    ]);

    expect($this->service->isEligibleForAllergenExclusions($this->item, ['peanut']))->toBeFalse();

    $this->service->save($this->item, $this->allergen, $this->admin, [
        ...$this->payload,
        'recipe_status' => 'reviewed_absent',
        'cross_contact_status' => 'reviewed_no_known_risk',
        'context' => $this->service->context($this->item, null),
    ]);
    $reviewedItem = $this->item->fresh();

    expect($this->service->isEligibleForAllergenExclusions($reviewedItem, ['peanut']))->toBeTrue();

    $reviewedItem->update(['name' => 'Grilled Herb Lamb', 'description' => 'Grilled lamb with herbs.']);
    $driftedItem = $reviewedItem->fresh();

    expect($this->service->isEligibleForAllergenExclusions($driftedItem, ['peanut']))->toBeFalse();
});

test('missing assessments use bilingual menu keyword matching and ignore clear negative phrases', function (string $allergenCode, string $name, string $description, bool $eligible) {
    $this->item->update(['name' => $name, 'description' => $description]);

    expect($this->service->isEligibleForAllergenExclusions($this->item, [$allergenCode]))->toBe($eligible);
})->with([
    'English peanut ingredient' => ['peanut', 'Satay Chicken', 'Served with peanut sauce.', false],
    'Malay peanut ingredient' => ['peanut', 'Grilled Chicken', 'Dihidangkan bersama kuah kacang.', false],
    'prawn ingredient' => ['crustacean_shellfish', 'Butter Prawn', 'Cooked with herbs.', false],
    'Malay prawn ingredient' => ['crustacean_shellfish', 'Nasi Goreng', 'Nasi goreng udang.', false],
    'explicit dairy-free wording' => ['milk', 'Dairy-free Coconut Rice', 'Prepared without dairy.', true],
    'explicit gluten-free wording' => ['wheat_gluten', 'Gluten-free Rice', 'Gluten-free preparation.', true],
    'unqualified bihun' => ['wheat_gluten', 'Bihun Goreng', 'Rice vermicelli with vegetables.', true],
    'wheat bihun' => ['wheat_gluten', 'Bihun', 'Made with wheat flour.', false],
]);

test('assessment management requires authenticated administrators', function (string $role) {
    if ($role !== 'guest') {
        $this->actingAs(User::factory()->create(['role' => $role]));
    }
    $responses = [
        $this->get(route('admin.menu.allergens.index', $this->item)),
        $this->get(route('admin.menu.allergens.edit', [$this->item, $this->allergen])),
        $this->patch($this->endpoint, $this->payload),
        $this->patch(route('admin.menu.allergens.invalidate', $this->item), ['change_type' => 'supplier', 'reason' => 'Changed']),
    ];
    foreach ($responses as $response) {
        $role === 'guest' ? $response->assertRedirect(route('admin.login')) : $response->assertForbidden();
    }
    $this->assertDatabaseCount('menu_item_allergen_assessments', 0);
})->with(['guest', 'customer']);

test('review saves authenticated provenance and keeps unassessed cross contact unknown', function () {
    $this->freezeTime();
    $original = $this->item->fresh()->getAttributes();
    $this->actingAs($this->admin)->patch($this->endpoint, $this->payload)->assertRedirect()->assertSessionHasNoErrors();
    $assessment = $this->item->allergenAssessments()->sole();
    expect($assessment->reviewed_by)->toBe($this->admin->id)
        ->and($assessment->reviewed_at->equalTo(now()))->toBeTrue()
        ->and($assessment->recipe_status)->toBe('contains')
        ->and($assessment->cross_contact_status)->toBe('unknown')
        ->and($assessment->isCurrentFor($this->item))->toBeTrue()
        ->and($this->item->fresh()->getAttributes())->toBe($original);
    $this->get(route('admin.menu.allergens.edit', [$this->item, $this->allergen]))
        ->assertOk()->assertSee($this->admin->name)->assertSee($this->payload['evidence'])->assertSee('UTC');
});

test('incomplete invalid or client forged review values are rejected accessibly', function (string $field, mixed $value) {
    $this->actingAs($this->admin)->from(route('admin.menu.allergens.edit', [$this->item, $this->allergen]))
        ->patch($this->endpoint, [...$this->payload, $field => $value])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('menu_item_allergen_assessments', 0);
    if (in_array($field, ['recipe_status', 'cross_contact_status', 'evidence', 'documented_information'])) {
        $this->withCookie(config('session.cookie'), session()->getId())->get(route('admin.menu.allergens.edit', [$this->item, $this->allergen]))
            ->assertSee('aria-invalid="true"', false)->assertSee($field.'-error');
    }
})->with([
    ['recipe_status', 'safe'], ['cross_contact_status', 'absent'], ['evidence', '   '],
    ['evidence', str_repeat('a', 2001)], ['documented_information', '0'], ['context', 'bad'],
    ['reviewed_by', 999], ['reviewed_at', '2020-01-01'], ['menu_fingerprint', str_repeat('a', 64)],
]);

test('reviewing the same pair updates one row and rejects stale submissions', function () {
    $this->actingAs($this->admin)->patch($this->endpoint, $this->payload)->assertSessionHasNoErrors();
    $this->patch($this->endpoint, $this->payload)->assertSessionHasErrors('context');
    $assessment = $this->item->allergenAssessments()->sole();
    $this->patch($this->endpoint, [...$this->payload, 'recipe_status' => 'reviewed_absent',
        'context' => $this->service->context($this->item, $assessment),
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('menu_item_allergen_assessments', 1);
    expect($assessment->fresh()->recipe_status)->toBe('reviewed_absent');
});

test('supplier preparation recipe and ingredient changes invalidate without erasing last evidence', function (string $type) {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $assessment = $this->item->allergenAssessments()->sole();
    $staleContext = $this->service->context($this->item, $assessment);
    $this->actingAs($this->admin)->patch(route('admin.menu.allergens.invalidate', $this->item), [
        'change_type' => $type, 'reason' => 'Test-only change reference B2',
    ])->assertSessionHasNoErrors();
    $assessment->refresh();
    expect($assessment->isCurrentFor($this->item))->toBeFalse()
        ->and($assessment->invalidated_by)->toBe($this->admin->id)
        ->and($assessment->evidence)->toBe($this->payload['evidence']);
    $this->get(route('admin.menu.allergens.index', $this->item))->assertSee('Invalidated or stale')->assertDontSee('Contains');
    $this->patch($this->endpoint, [...$this->payload, 'context' => $staleContext])->assertSessionHasErrors('context');
    $this->item->refresh();
    $this->patch($this->endpoint, [...$this->payload, 'context' => $this->service->context($this->item, $assessment)])
        ->assertSessionHasNoErrors();
    expect($assessment->fresh()->isCurrentFor($this->item))->toBeTrue();
})->with(['supplier', 'preparation', 'recipe', 'ingredients']);

test('invalidation requires a supported change type and reason', function () {
    $this->actingAs($this->admin)->patch(route('admin.menu.allergens.invalidate', $this->item), [
        'change_type' => 'price', 'reason' => '',
    ])->assertSessionHasErrors(['change_type', 'reason']);
});

test('recipe related menu edits invalidate atomically but commercial edits do not', function (string $field, mixed $value, bool $invalidated) {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $data = $this->item->only(['name', 'slug', 'menu_category_id', 'description', 'price', 'spice_level',
        'protein_type', 'dietary_type', 'flavor_profile', 'is_available', 'is_featured', 'sort_order']);
    $this->actingAs($this->admin)->patch(route('admin.menu.items.update', $this->item), [...$data, $field => $value])
        ->assertRedirect()->assertSessionHasNoErrors();
    $assessment = $this->item->allergenAssessments()->sole();
    expect($assessment->invalidated_at !== null)->toBe($invalidated)
        ->and($assessment->isCurrentFor($this->item->fresh()))->toBe(! $invalidated);
})->with([
    ['description', 'Changed documented recipe description', true], ['name', 'Changed test dish', true],
    ['price', '19.99', false], ['is_available', false, false],
]);

test('direct metadata changes fail closed and reject an old review form', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $assessment = $this->item->allergenAssessments()->sole();
    $context = $this->service->context($this->item, $assessment);
    $this->item->update(['description' => 'Changed source']);
    expect($assessment->isCurrentFor($this->item))->toBeFalse();
    $this->actingAs($this->admin)->patch($this->endpoint, [...$this->payload, 'context' => $context])->assertSessionHasErrors('context');
});

test('reviews do not automatically expire and evidence is escaped', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, [...$this->payload, 'evidence' => '<script>alert(1)</script>']);
    $this->travel(400)->days();
    expect($this->item->allergenAssessments()->sole()->isCurrentFor($this->item))->toBeTrue();
    $this->actingAs($this->admin)->get(route('admin.menu.allergens.edit', [$this->item, $this->allergen]))
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

test('database enforces pair uniqueness foreign keys statuses and provenance', function (array $changes) {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $row = $this->item->allergenAssessments()->sole()->getAttributes();
    unset($row['id']);
    expect(fn () => DB::table('menu_item_allergen_assessments')->insert([...$row, ...$changes]))->toThrow(QueryException::class);
})->with([
    [[]], [['menu_item_id' => 99999999]], [['allergen_id' => 99999999]],
    [['allergen_id' => 2, 'reviewed_by' => 99999999]], [['allergen_id' => 2, 'evidence' => '']],
    [['allergen_id' => 2, 'reviewed_at' => null]], [['allergen_id' => 2, 'recipe_status' => 'safe']],
    [['allergen_id' => 2, 'invalidated_at' => '2026-10-16 00:00:00']],
]);

test('failed assessment save rolls back the inserted row', function () {
    $event = 'eloquent.created: '.MenuItemAllergenAssessment::class;
    $listener = fn () => throw new RuntimeException('Test-only interrupted save');
    app('events')->listen($event, $listener);
    try {
        expect(fn () => $this->service->save($this->item, $this->allergen, $this->admin, $this->payload))
            ->toThrow(RuntimeException::class, 'Test-only interrupted save');
        $this->assertDatabaseCount('menu_item_allergen_assessments', 0);
    } finally {
        app('events')->forget($event);
    }
});

test('failed invalidation rolls back the related menu edit', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $original = $this->item->fresh()->getAttributes();
    $service = Mockery::mock(MenuAllergenAssessmentService::class);
    $service->shouldReceive('invalidate')->once()->andThrow(new RuntimeException('Test-only invalidation failure'));
    app()->instance(MenuAllergenAssessmentService::class, $service);
    $data = $this->item->only(['name', 'slug', 'menu_category_id', 'description', 'price', 'spice_level',
        'protein_type', 'dietary_type', 'flavor_profile', 'is_available', 'is_featured', 'sort_order']);
    $this->withoutExceptionHandling()->actingAs($this->admin);
    expect(fn () => $this->patch(route('admin.menu.items.update', $this->item), [...$data, 'description' => 'Changed recipe']))
        ->toThrow(RuntimeException::class, 'Test-only invalidation failure');
    expect($this->item->fresh()->getAttributes())->toBe($original)
        ->and($this->item->allergenAssessments()->sole()->isCurrentFor($this->item))->toBeTrue();
});

test('explicit unknown assessments and incomplete provenance never imply verified absence', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, [...$this->payload,
        'recipe_status' => 'unknown', 'cross_contact_status' => 'unknown',
    ]);
    $this->actingAs($this->admin)->get(route('admin.menu.allergens.index', $this->item))
        ->assertSee('Unknown')->assertDontSee('Reviewed absent')->assertDontSee('Reviewed no known risk');
    $assessment = $this->item->allergenAssessments()->sole();
    $assessment->evidence = '';
    expect($assessment->isCurrentFor($this->item))->toBeFalse();
});

test('pre-first-review changes reject old forms without creating assessment facts', function () {
    $this->freezeTime();
    $this->actingAs($this->admin)->patch(route('admin.menu.allergens.invalidate', $this->item), [
        'change_type' => 'supplier', 'reason' => 'Synthetic test change before first review',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('menu_item_allergen_assessments', 0);
    $this->patch($this->endpoint, $this->payload)->assertSessionHasErrors('context');
    $fresh = $this->item->fresh();
    $this->patch($this->endpoint, [...$this->payload, 'context' => $this->service->context($fresh, null)])
        ->assertSessionHasNoErrors();
    expect($fresh->allergenAssessments()->sole()->isCurrentFor($fresh))->toBeTrue();
});

test('repeated invalidation rejects intervening review forms even within the same second', function () {
    $this->freezeTime();
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $this->service->invalidate($this->item, $this->admin, 'Synthetic preparation change');
    $assessment = $this->item->allergenAssessments()->sole();
    $provenance = $assessment->only(['reviewed_by', 'reviewed_at', 'evidence']);
    $context = $this->service->context($this->item->fresh(), $assessment);
    $this->service->invalidate($this->item, $this->admin, 'Synthetic preparation change');
    $this->actingAs($this->admin)->patch($this->endpoint, [...$this->payload, 'context' => $context])
        ->assertSessionHasErrors('context');
    expect($assessment->fresh()->only(['reviewed_by', 'reviewed_at', 'evidence']))->toEqual($provenance);
    $fresh = $this->item->fresh();
    $this->patch($this->endpoint, [...$this->payload, 'context' => $this->service->context($fresh, $assessment->fresh())])
        ->assertSessionHasNoErrors();
    expect($assessment->fresh()->isCurrentFor($fresh))->toBeTrue();
    $this->service->invalidate($fresh, $this->admin, 'Another synthetic change after re-review');
    expect($assessment->fresh()->isCurrentFor($fresh->fresh()))->toBeFalse();
});

test('reverting menu metadata cannot revive a pre-first-review form', function () {
    $this->freezeTime();
    $data = $this->item->only(['name', 'slug', 'menu_category_id', 'description', 'price', 'spice_level',
        'protein_type', 'dietary_type', 'flavor_profile', 'is_available', 'is_featured', 'sort_order']);
    $this->actingAs($this->admin)->patch(route('admin.menu.items.update', $this->item), [...$data, 'description' => 'Synthetic recipe change'])
        ->assertSessionHasNoErrors();
    $this->patch(route('admin.menu.items.update', $this->item), $data)->assertSessionHasNoErrors();
    $this->patch($this->endpoint, $this->payload)->assertSessionHasErrors('context');
    $this->assertDatabaseCount('menu_item_allergen_assessments', 0);
});

test('item changes also reject old forms for an allergen that has never been reviewed', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $other = Allergen::where('code', 'milk')->firstOrFail();
    $oldContext = $this->service->context($this->item->fresh(), null);
    $this->service->invalidate($this->item, $this->admin, 'Synthetic ingredient change');
    $this->actingAs($this->admin)->patch(route('admin.menu.allergens.update', [$this->item, $other]), [
        ...$this->payload, 'context' => $oldContext,
    ])->assertSessionHasErrors('context');
    $this->assertDatabaseCount('menu_item_allergen_assessments', 1);
    $this->assertDatabaseMissing('menu_item_allergen_assessments', ['menu_item_id' => $this->item->id, 'allergen_id' => $other->id]);
});

test('failed invalidation rolls back its revision and leaves the prior review current', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, $this->payload);
    $before = $this->item->fresh();
    $assessment = $before->allergenAssessments()->sole();
    $context = $this->service->context($before, $assessment);
    expect(fn () => $this->service->invalidate($this->item, $this->admin, ''))
        ->toThrow(QueryException::class);
    $fresh = $this->item->fresh();
    expect($fresh->getAttributes())->toBe($before->getAttributes());
    expect($this->service->context($fresh, $assessment->fresh()))->toBe($context);
    expect($assessment->fresh()->isCurrentFor($fresh))->toBeTrue();
});

test('recorded dual-axis review permits a candidate while presenting the shared-kitchen limitation', function () {
    $this->service->save($this->item, $this->allergen, $this->admin, [...$this->payload,
        'recipe_status' => 'reviewed_absent', 'cross_contact_status' => 'reviewed_no_known_risk',
    ]);
    $this->post(route('ai.recommendations.recommend'), ['preferences_text' => 'peanut allergy'])
        ->assertOk()
        ->assertSee('Please tell restaurant staff before ordering.')
        ->assertSee('Menu text may be incomplete and does not confirm ingredients or shared-kitchen handling.')
        ->assertSee('Reviewed: Peanut not listed in recipe · No known cross-contact risk')
        ->assertViewHas('recommendations', fn ($items) => $items->pluck('id')->all() === [$this->item->id]);
});
