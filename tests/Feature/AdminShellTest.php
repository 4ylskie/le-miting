<?php

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminShellXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

test('allergen assessment pages identify menu items as their owning navigation section', function (string $routeName) {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = MenuItem::factory()->create();
    $parameters = ['menuItem' => $item];
    if ($routeName === 'admin.menu.allergens.edit') {
        $parameters['allergen'] = Allergen::query()->firstOrFail();
    }

    $response = $this->actingAs($admin)->get(route($routeName, $parameters))->assertOk();
    $xpath = adminShellXPath($response->getContent());

    expect($xpath->query('//nav[@aria-label="Admin navigation"]//a[@aria-current="page"]')->length)->toBe(1);
    expect($xpath->evaluate('string(//nav[@aria-label="Admin navigation"]//a[@aria-current="page"]/@href)'))->toBe(route('admin.menu.items.index'));
})->with(['admin.menu.allergens.index', 'admin.menu.allergens.edit']);

test('admin pages retain the shared shell and highlight their owning navigation section', function (string $routeName, string $activeRoute, ?string $modelClass) {
    $admin = User::factory()->create(['role' => 'admin']);
    $parameters = $modelClass ? [$modelClass::factory()->create()] : [];

    $response = $this->actingAs($admin)->get(route($routeName, $parameters))->assertOk();
    $xpath = adminShellXPath($response->getContent());
    $navigation = $xpath->query('//nav[@aria-label="Admin navigation"]')->item(0);

    expect($xpath->query('.//a', $navigation)->length)->toBe(7);
    expect($xpath->query('.//a[@aria-current="page"]', $navigation)->length)->toBe(1);
    expect($xpath->evaluate('string(.//a[@aria-current="page"]/@href)', $navigation))->toBe(route($activeRoute));
    expect($xpath->query('//main[@id="admin-main" and @tabindex="-1"]')->length)->toBe(1);
    expect($xpath->query('//canvas[@id="lm-culinary-shader"]')->length)->toBe(0);
    expect($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " lm-shader-grain ") and @aria-hidden="true"]')->length)->toBe(1);
    expect($xpath->query('//script[contains(@src, "/js/culinary-shader.js")]')->length)->toBe(0);
    expect($xpath->query('//script[not(@src)]')->length)->toBe(0);
    expect($xpath->query('//@*[starts-with(translate(name(), "ON", "on"), "on")]')->length)->toBe(0);
    expect($xpath->query('//dialog[@id="admin-navigation-dialog" and @aria-label]')->length)->toBe(1);
    expect($xpath->query('//button[@data-admin-open and @aria-controls="admin-navigation-dialog" and @aria-expanded="false"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="admin-navigation"]//button[@data-admin-close]')->length)->toBe(1);
    $logout = $xpath->query('//*[@id="admin-navigation"]//form')->item(0);
    expect($logout->getAttribute('action'))->toBe(route('admin.logout'));
    expect($logout->getAttribute('method'))->toBe('POST');
    expect($xpath->query('.//input[@name="_token"]', $logout)->length)->toBe(1);
})->with([
    'dashboard' => ['admin.dashboard', 'admin.dashboard', null],
    'orders' => ['admin.orders.index', 'admin.orders.index', null],
    'order details' => ['admin.orders.show', 'admin.orders.index', Order::class],
    'reservations' => ['admin.reservations.index', 'admin.reservations.index', null],
    'reservation details' => ['admin.reservations.show', 'admin.reservations.index', Reservation::class],
    'tables' => ['admin.tables.index', 'admin.tables.index', null],
    'create table' => ['admin.tables.create', 'admin.tables.index', null],
    'edit table' => ['admin.tables.edit', 'admin.tables.index', Table::class],
    'items' => ['admin.menu.items.index', 'admin.menu.items.index', null],
    'create item' => ['admin.menu.items.create', 'admin.menu.items.index', null],
    'edit item' => ['admin.menu.items.edit', 'admin.menu.items.index', MenuItem::class],
    'categories' => ['admin.menu.categories.index', 'admin.menu.categories.index', null],
    'create category' => ['admin.menu.categories.create', 'admin.menu.categories.index', null],
    'edit category' => ['admin.menu.categories.edit', 'admin.menu.categories.index', MenuCategory::class],
    'reports' => ['admin.reports.index', 'admin.reports.index', null],
]);

test('admin login keeps its existing fields and exposes accessible validation feedback', function () {
    $this->from(route('admin.login'))->post(route('admin.login.submit'), [
        'email' => 'not-an-email', 'password' => '', 'remember' => '1',
    ])->assertSessionHasErrors(['email', 'password']);

    $response = $this->withCookie(config('session.cookie'), session()->getId())
        ->get(route('admin.login'))->assertOk();
    $xpath = adminShellXPath($response->getContent());
    expect($xpath->query('//h1')->length)->toBe(1);
    expect($xpath->evaluate('string(//main//form/@action)'))->toBe(route('admin.login.submit'));
    expect($xpath->query('//main//form//input[@name="_token"]')->length)->toBe(1);
    expect($xpath->query('//canvas[@id="lm-culinary-shader"]')->length)->toBe(0);
    expect($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " lm-shader-grain ") and @aria-hidden="true"]')->length)->toBe(1);
    expect($xpath->query('//script[contains(@src, "/js/culinary-shader.js")]')->length)->toBe(0);
    foreach (['email', 'password'] as $field) {
        $input = $xpath->query('//input[@name="'.$field.'"]')->item(0);
        expect($input->getAttribute('aria-invalid'))->toBe('true');
        expect($xpath->query('//*[@id="'.$input->getAttribute('aria-describedby').'" and @role="alert"]')->length)->toBe(1);
    }
    expect($xpath->evaluate('string(//input[@name="email"]/@value)'))->toBe('not-an-email');
    expect($xpath->query('//input[@name="password" and @value]')->length)->toBe(0);
    expect($xpath->query('//input[@name="remember" and @checked]')->length)->toBe(1);
});
