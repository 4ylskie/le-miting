<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin filter forms provide explicit submission without select change navigation', function (string $routeName, string $field) {
    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->get(route($routeName))->assertOk();
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $form = $xpath->query('//form[@method="GET" and @action="'.route($routeName).'"]')->item(0);

    expect($form)->not->toBeNull();
    expect($xpath->query('.//select[@name="'.$field.'" and not(@onchange)]', $form)->length)->toBe(1);
    expect($xpath->query('.//button[@type="submit" and contains(., "Apply filters")]', $form)->length)->toBe(1);
    expect($xpath->query('.//label[@for="'.$field.'"]', $form)->length)->toBe(1);
})->with([
    'orders' => ['admin.orders.index', 'status'],
    'reservations' => ['admin.reservations.index', 'status'],
    'menu category' => ['admin.menu.items.index', 'category'],
]);

test('order filters use the same supported statuses for results selection and empty states', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $match = Order::factory()->create(['status' => $status]);
    Order::factory()->create(['status' => $status === 'pending' ? 'completed' : 'pending']);

    $response = $this->actingAs($admin)->get(route('admin.orders.index', ['status' => $status]))->assertOk()
        ->assertViewHas('currentStatus', $status);
    expect($response->viewData('orders')->pluck('id')->all())->toBe([$match->id]);
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    expect((new DOMXPath($document))->query('//select[@name="status"]/option[@value="'.$status.'" and @selected]')->length)->toBe(1);

    $match->delete();
    $this->get(route('admin.orders.index', ['status' => $status]))->assertOk()
        ->assertSee('No orders with status “'.$status.'” right now.');
})->with(['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled']);

test('missing or unsupported order filters consistently show all orders', function (array $query) {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.orders.index', $query))->assertOk()
        ->assertViewHas('currentStatus', null)->assertSee('No orders yet. Orders appear here when guests check out.');

    Order::factory()->create(['status' => 'pending']);
    Order::factory()->create(['status' => 'completed']);
    $response = $this->get(route('admin.orders.index', $query))->assertOk()->assertViewHas('currentStatus', null);
    expect($response->viewData('orders')->total())->toBe(2);
})->with([
    'missing' => [[]],
    'empty' => [['status' => '']],
    'unknown' => [['status' => 'not-a-status']],
    'case mismatch' => [['status' => 'PENDING']],
    'array' => [['status' => ['pending']]],
]);

test('order pagination preserves combined parameters while dropping unsupported status values', function (string $status, ?string $expected) {
    $admin = User::factory()->create(['role' => 'admin']);
    Order::factory()->count(17)->create(['status' => 'pending']);
    $response = $this->actingAs($admin)->get(route('admin.orders.index', [
        'status' => $status, 'page' => 2, 'context' => 'review',
    ]))->assertOk()->assertViewHas('currentStatus', $expected);
    $orders = $response->viewData('orders');

    expect($orders->total())->toBe(17);
    expect($orders->count())->toBe(2);
    parse_str(parse_url($orders->previousPageUrl(), PHP_URL_QUERY), $parameters);
    expect($parameters['page'])->toBe('1');
    expect($parameters['context'])->toBe('review');
    expect($parameters['status'] ?? null)->toBe($expected);
})->with([
    'valid' => ['pending', 'pending'],
    'invalid' => ['not-a-status', null],
]);

test('menu category and search parameters combine and persist across pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = MenuCategory::factory()->create();
    MenuItem::factory()->count(16)->for($category, 'category')->create(['name' => 'Matching filter dish']);
    MenuItem::factory()->create(['name' => 'Matching other category']);
    MenuItem::factory()->for($category, 'category')->create(['name' => 'Excluded dish']);

    $response = $this->actingAs($admin)->get(route('admin.menu.items.index', [
        'category' => $category->id, 'q' => 'Matching', 'page' => 2,
    ]))->assertOk()->assertViewHas('search', 'Matching')->assertViewHas('currentCategory', (string) $category->id);
    $items = $response->viewData('items');
    expect($items->total())->toBe(16);
    expect($items->count())->toBe(1);
    parse_str(parse_url($items->previousPageUrl(), PHP_URL_QUERY), $parameters);
    expect($parameters)->toMatchArray(['category' => (string) $category->id, 'q' => 'Matching', 'page' => '1']);
});

test('reservation status and number search combine and persist across pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $table = Table::factory()->create();
    Reservation::factory()->count(16)->for($table)->sequence(fn ($sequence) => [
        'reservation_number' => 'RES-FILTER-'.str_pad((string) $sequence->index, 4, '0', STR_PAD_LEFT),
    ])->create(['status' => 'confirmed']);
    Reservation::factory()->for($table)->create(['reservation_number' => 'RES-FILTER-OTHER', 'status' => 'pending']);
    Reservation::factory()->for($table)->create(['reservation_number' => 'RES-EXCLUDED', 'status' => 'confirmed']);

    $response = $this->actingAs($admin)->get(route('admin.reservations.index', [
        'status' => 'confirmed', 'search' => 'RES-FILTER', 'page' => 2,
    ]))->assertOk()->assertViewHas('search', 'RES-FILTER')->assertViewHas('currentStatus', 'confirmed');
    $reservations = $response->viewData('reservations');
    expect($reservations->total())->toBe(16);
    expect($reservations->count())->toBe(1);
    parse_str(parse_url($reservations->previousPageUrl(), PHP_URL_QUERY), $parameters);
    expect($parameters)->toMatchArray(['status' => 'confirmed', 'search' => 'RES-FILTER', 'page' => '1']);
});
