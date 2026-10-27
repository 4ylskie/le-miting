<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function categoryFormDocument(string $html): HTMLDocument
{
    return HTMLDocument::createFromString($html, LIBXML_NOERROR);
}

/** @return array<string, string> */
function categoryFormInputs(Element $form): array
{
    $pairs = [];

    foreach ($form->querySelectorAll('input[name]') as $input) {
        if ($input->getAttribute('type') === 'checkbox' && ! $input->hasAttribute('checked')) {
            continue;
        }

        $pairs[] = urlencode($input->getAttribute('name')).'='.urlencode($input->getAttribute('value'));
    }

    parse_str(implode('&', $pairs), $inputs);

    return $inputs;
}

test('rendered category forms persist the selected active state', function (?bool $initial, bool $checked) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = $initial === null ? null : MenuCategory::factory()->create(['is_active' => $initial]);
    $url = $category ? route('admin.menu.categories.edit', $category) : route('admin.menu.categories.create');
    $response = $this->actingAs($admin)->get($url)->assertOk();
    $document = categoryFormDocument($response->getContent());
    $form = $document->querySelector('main form');
    $checkbox = $form->querySelector('#is_active');

    expect($checkbox->hasAttribute('checked'))->toBe($initial ?? true);
    if ($checked) {
        $checkbox->setAttribute('checked', '');
    } else {
        $checkbox->removeAttribute('checked');
    }
    $form->querySelector('[name="name"]')->setAttribute('value', 'Checkbox fixture');
    $form->querySelector('[name="slug"]')->setAttribute('value', 'checkbox-fixture');

    $this->post($form->getAttribute('action'), categoryFormInputs($form))
        ->assertRedirect(route('admin.menu.categories.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('menu_categories', ['slug' => 'checkbox-fixture', 'is_active' => $checked]);
    $controls = $form->querySelectorAll('[name="is_active"]');
    expect($controls)->toHaveCount(2);
    expect($controls->item(0)->getAttribute('type'))->toBe('hidden');
    expect($controls->item(0)->getAttribute('value'))->toBe('0');
    expect($controls->item(1)->getAttribute('type'))->toBe('checkbox');
    expect($controls->item(1)->getAttribute('value'))->toBe('1');
    expect($controls->item(0)->nextElementSibling->isSameNode($checkbox))->toBeTrue();
    foreach ($controls as $control) {
        expect($control->closest('form')->isSameNode($form))->toBeTrue();
        expect($control->hasAttribute('form'))->toBeFalse();
    }
    expect($document->querySelectorAll('#category-delete-form [name="is_active"]'))->toHaveCount(0);
})->with([
    'create active' => [null, true],
    'create inactive' => [null, false],
    'active to inactive' => [true, false],
    'inactive to active' => [false, true],
    'active to active' => [true, true],
    'inactive to inactive' => [false, false],
]);

test('category validation errors restore the submitted checkbox state', function (?bool $initial, bool $checked) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = $initial === null ? null : MenuCategory::factory()->create(['is_active' => $initial]);
    $original = $category?->fresh()->getAttributes();
    $url = $category ? route('admin.menu.categories.edit', $category) : route('admin.menu.categories.create');
    $response = $this->actingAs($admin)->get($url);
    $form = categoryFormDocument($response->getContent())->querySelector('main form');
    $checkbox = $form->querySelector('#is_active');
    if ($checked) {
        $checkbox->setAttribute('checked', '');
    } else {
        $checkbox->removeAttribute('checked');
    }
    $form->querySelector('[name="name"]')->setAttribute('value', 'Unsaved checkbox fixture');
    $form->querySelector('[name="slug"]')->setAttribute('value', 'Invalid Slug!');

    $this->from($url)->post($form->getAttribute('action'), categoryFormInputs($form))
        ->assertRedirect($url)
        ->assertSessionHasErrors('slug')
        ->assertSessionHasInput('is_active', $checked ? '1' : '0');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($url)->assertOk();
    $document = categoryFormDocument($response->getContent());
    expect($document->querySelector('#is_active')->hasAttribute('checked'))->toBe($checked);
    expect(trim($document->querySelector('[role="alert"]')->textContent))->toBe('The slug may only contain lowercase letters, numbers, and dashes.');
    if ($category) {
        expect($category->fresh()->getAttributes())->toBe($original);
    } else {
        $this->assertDatabaseCount('menu_categories', 0);
    }
})->with([
    'unchecking an active category' => [true, false],
    'checking an inactive category' => [false, true],
    'creating an inactive category' => [null, false],
]);

test('category create renders a standalone protected store form', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.menu.categories.create'))->assertOk();
    $document = categoryFormDocument($response->getContent());
    $form = $document->querySelector('main form');

    expect($document->querySelectorAll('main form'))->toHaveCount(1);
    expect($form->getAttribute('action'))->toBe(route('admin.menu.categories.store'));
    expect($form->getAttribute('method'))->toBe('POST');
    expect(categoryFormInputs($form)['_token'])->toBe(session()->token());
    expect($form->querySelector('input[name="_method"]'))->toBeNull();
    expect($document->querySelector('button[form]'))->toBeNull();
});

test('category edit renders independent update and delete forms with correctly owned controls', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();

    $response = $this->actingAs($admin)->get(route('admin.menu.categories.edit', $category))->assertOk();
    $html = $response->getContent();
    $document = categoryFormDocument($html);
    $update = $document->querySelector('main form');
    $delete = $document->querySelector('#category-delete-form');

    expect($document->querySelectorAll('main form'))->toHaveCount(2);
    expect($document->querySelectorAll('form form'))->toHaveCount(0);
    expect($update->getAttribute('action'))->toBe(route('admin.menu.categories.update', $category));
    expect($update->getAttribute('method'))->toBe('POST');
    expect($update->querySelectorAll('input[name="_method"]'))->toHaveCount(1);
    expect(categoryFormInputs($update)['_method'])->toBe('PATCH');
    expect(categoryFormInputs($update)['_token'])->toBe(session()->token());
    expect(trim($update->querySelector('button[type="submit"]:not([form])')->textContent))->toBe('Save changes');
    expect($delete->getAttribute('action'))->toBe(route('admin.menu.categories.destroy', $category));
    expect($delete->getAttribute('method'))->toBe('POST');
    expect(categoryFormInputs($delete))->toBe(['_token' => session()->token(), '_method' => 'DELETE']);
    expect($delete->getAttribute('data-confirm-submit'))->toBe('Delete this category?');
    expect($delete->getAttribute('onsubmit'))->toBeNull();
    expect(trim($document->querySelector('button[type="submit"][form="category-delete-form"]')->textContent))->toBe('Delete category');

    // Check the rendered tokens too: HTML5 parsing repairs nested forms and can hide the defect.
    preg_match_all('/<\/?form\b[^>]*>/i', $html, $tags);
    $depth = 0;

    foreach ($tags[0] as $tag) {
        $depth += str_starts_with($tag, '</') ? -1 : 1;
        expect($depth)->toBeIn([0, 1]);
    }

    expect($depth)->toBe(0);
});

test('category edit with items keeps its update form without a delete action', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->create(['menu_category_id' => $category->id]);

    $response = $this->actingAs($admin)->get(route('admin.menu.categories.edit', $category))->assertOk();
    $document = categoryFormDocument($response->getContent());

    expect($document->querySelectorAll('main form'))->toHaveCount(1);
    expect($document->querySelector('#category-delete-form'))->toBeNull();
    expect($document->querySelector('button[form]'))->toBeNull();
    expect(categoryFormInputs($document->querySelector('main form'))['_method'])->toBe('PATCH');
    $response->assertSee('View items');
});

test('submitting the rendered update form updates rather than deletes the category', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    $response = $this->actingAs($admin)->get(route('admin.menu.categories.edit', $category));
    $form = categoryFormDocument($response->getContent())->querySelector('main form');
    $inputs = array_replace(categoryFormInputs($form), ['name' => 'Updated Category', 'slug' => 'updated-category']);

    $this->post($form->getAttribute('action'), $inputs)
        ->assertRedirect(route('admin.menu.categories.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Category updated.');

    $this->assertDatabaseHas('menu_categories', ['id' => $category->id, 'name' => 'Updated Category', 'slug' => 'updated-category']);
});

test('submitting the rendered delete form deletes without submitting update fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    $response = $this->actingAs($admin)->get(route('admin.menu.categories.edit', $category));
    $form = categoryFormDocument($response->getContent())->querySelector('#category-delete-form');

    $this->post($form->getAttribute('action'), categoryFormInputs($form))
        ->assertRedirect(route('admin.menu.categories.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Category “'.$category->name.'” deleted.');

    $this->assertModelMissing($category);
});

test('invalid category updates retain old input and visible validation feedback', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['name' => 'Original Category', 'slug' => 'original-category']);
    $editUrl = route('admin.menu.categories.edit', $category);

    $this->actingAs($admin)->from($editUrl)->patch(route('admin.menu.categories.update', $category), [
        'name' => 'Unsaved Category',
        'slug' => 'Invalid Slug!',
        'sort_order' => 7,
    ])->assertRedirect($editUrl)
        ->assertSessionHasErrors(['slug' => 'The slug may only contain lowercase letters, numbers, and dashes.'])
        ->assertSessionHasInput('name', 'Unsaved Category')
        ->assertSessionHasInput('slug', 'Invalid Slug!');

    $response = $this->withCookie(config('session.cookie'), session()->getId())
        ->get($editUrl)->assertOk();
    $document = categoryFormDocument($response->getContent());
    expect($document->querySelector('input[name="name"]')->getAttribute('value'))->toBe('Unsaved Category');
    expect($document->querySelector('input[name="slug"]')->getAttribute('value'))->toBe('Invalid Slug!');
    expect($document->querySelector('input[name="sort_order"]')->getAttribute('value'))->toBe('7');
    expect(trim($document->querySelector('[role="alert"]')->textContent))->toBe('The slug may only contain lowercase letters, numbers, and dashes.');
    expect($document->querySelectorAll('main form'))->toHaveCount(2);
    $this->assertDatabaseHas('menu_categories', ['id' => $category->id, 'name' => 'Original Category', 'slug' => 'original-category']);
});

test('category deletion still refuses soft deleted item references', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->create(['menu_category_id' => $category->id])->delete();

    $this->actingAs($admin)->delete(route('admin.menu.categories.destroy', $category))
        ->assertSessionHasErrors('category');

    $this->assertModelExists($category);
});

test('category list and edit show every assigned item state and agree on deletion eligibility', function (array $items, int $current, int $archived) {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['name' => 'Assignment fixture']);

    foreach ($items as $itemState) {
        $item = MenuItem::factory()->for($category, 'category')->create([
            'is_available' => $itemState !== 'unavailable',
        ]);

        if ($itemState === 'archived') {
            $item->delete();
        }
    }

    $list = $this->actingAs($admin)->get(route('admin.menu.categories.index'))->assertOk();
    $listDocument = categoryFormDocument($list->getContent());
    $row = $listDocument->querySelector('main table tbody tr');
    expect($row->textContent)->toContain('Assignment fixture');
    expect(trim($row->querySelector('td:nth-child(3)')->textContent))->toBe((string) $current);
    expect(trim($row->querySelector('td:nth-child(4)')->textContent))->toBe((string) $archived);
    expect(trim($row->querySelector('td:nth-child(5)')->textContent))->toBe((string) ($current + $archived));

    $edit = $this->get(route('admin.menu.categories.edit', $category))->assertOk();
    $editDocument = categoryFormDocument($edit->getContent());
    $guidance = $editDocument->querySelector('main')->textContent;
    expect($guidance)->toContain("Not archived: {$current}");
    expect($guidance)->toContain("Archived: {$archived}");
    expect($guidance)->toContain('Total assigned: '.($current + $archived));

    if ($current + $archived === 0) {
        expect($editDocument->querySelector('#category-delete-form'))->not->toBeNull();
        expect($guidance)->toContain('This category has no assigned items and can be deleted.');

        return;
    }

    expect($editDocument->querySelector('#category-delete-form'))->toBeNull();
    expect($editDocument->querySelector('button[form="category-delete-form"]'))->toBeNull();
    expect($guidance)->toContain('including archived items');
    expect($guidance)->toContain('Making an item unavailable or removing it from the public menu does not clear that reference.');

    $this->delete(route('admin.menu.categories.destroy', $category))
        ->assertSessionHasErrors('category');
    $this->assertModelExists($category);
    $this->assertDatabaseCount('menu_items', count($items));
})->with([
    'empty' => [[], 0, 0],
    'available' => [['available'], 1, 0],
    'unavailable but not archived' => [['unavailable'], 1, 0],
    'only archived' => [['archived'], 0, 1],
    'mixed' => [['available', 'unavailable', 'archived'], 2, 1],
]);

test('blocked deletion from the edit page preserves its message and referenced records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create(['name' => 'Archived category']);
    $item = MenuItem::factory()->for($category, 'category')->create();
    $order = Order::factory()->create();
    $orderItem = $order->orderItems()->create([
        'menu_item_id' => $item->id,
        'item_name' => 'Historical dish',
        'unit_price' => '12.50',
        'quantity' => 1,
    ]);
    $item->delete();
    $original = MenuItem::withTrashed()->findOrFail($item->id)->getAttributes();
    $editUrl = route('admin.menu.categories.edit', $category);

    $this->actingAs($admin)->from($editUrl)->delete(route('admin.menu.categories.destroy', $category))
        ->assertRedirect($editUrl)
        ->assertSessionHasErrors(['category' => 'Category “Archived category” still has 1 assigned menu item. Archived items still reference the category; making an item unavailable or removing it from the public menu does not clear that reference.']);

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($editUrl)->assertOk();
    $document = categoryFormDocument($response->getContent());
    expect(trim($document->querySelector('[role="alert"]')->textContent))
        ->toBe('Category “Archived category” still has 1 assigned menu item. Archived items still reference the category; making an item unavailable or removing it from the public menu does not clear that reference.');
    expect($document->querySelector('#category-delete-form'))->toBeNull();
    expect(MenuItem::withTrashed()->findOrFail($item->id)->getAttributes())->toBe($original);
    $this->assertDatabaseHas('order_items', [
        'id' => $orderItem->id,
        'menu_item_id' => $item->id,
        'item_name' => 'Historical dish',
        'unit_price' => '12.50',
    ]);
    $this->assertModelExists($category);
});

test('guests cannot submit category update or deletion', function (string $method) {
    $category = MenuCategory::factory()->create(['name' => 'Protected Category']);

    $this->post(route('admin.menu.categories.update', $category), [
        '_method' => $method, 'name' => 'Changed', 'slug' => 'changed', 'is_active' => '0',
    ])->assertRedirect(route('admin.login'));

    $this->assertDatabaseHas('menu_categories', ['id' => $category->id, 'name' => 'Protected Category', 'is_active' => 1]);
})->with(['update' => 'PATCH', 'delete' => 'DELETE']);

test('non administrators cannot submit category update or deletion', function (string $method) {
    $user = User::factory()->create(['role' => 'customer']);
    $category = MenuCategory::factory()->create(['name' => 'Protected Category']);

    $this->actingAs($user)->post(route('admin.menu.categories.update', $category), [
        '_method' => $method, 'name' => 'Changed', 'slug' => 'changed', 'is_active' => '0',
    ])->assertForbidden();

    $this->assertDatabaseHas('menu_categories', ['id' => $category->id, 'name' => 'Protected Category', 'is_active' => 1]);
})->with(['update' => 'PATCH', 'delete' => 'DELETE']);
