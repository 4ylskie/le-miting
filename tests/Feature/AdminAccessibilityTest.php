<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminAccessibilityXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

test('admin forms associate each validation error with its field', function (string $routeName, string $storeRoute, array $invalidInput, array $fields) {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->from(route($routeName))->post(route($storeRoute), $invalidInput)
        ->assertRedirect(route($routeName))->assertSessionHasErrors($fields);

    $response = $this
        ->withCookie(config('session.cookie'), session()->getId())
        ->get(route($routeName))->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());

    foreach ($fields as $field) {
        $input = $xpath->query('//*[@name="'.$field.'" and @aria-invalid="true"]')->item(0);
        expect($input, $field)->not->toBeNull();
        $errorId = $input->getAttribute('aria-describedby');
        expect($errorId)->not->toBeEmpty();
        expect($xpath->query('//*[@id="'.$errorId.'" and @role="alert"]')->length)->toBe(1);
    }
})->with([
    'category' => ['admin.menu.categories.create', 'admin.menu.categories.store', ['name' => '', 'slug' => '', 'sort_order' => '-1', 'is_active' => 'invalid'], ['name', 'slug', 'sort_order', 'is_active']],
    'table' => ['admin.tables.create', 'admin.tables.store', ['table_number' => '', 'capacity' => '999', 'status' => 'invalid'], ['table_number', 'capacity', 'status']],
    'menu item' => ['admin.menu.items.create', 'admin.menu.items.store', ['name' => '', 'slug' => '', 'menu_category_id' => '999999', 'price' => '-1', 'description' => '', 'image' => 'invalid', 'spice_level' => 'invalid', 'protein_type' => 'invalid', 'dietary_type' => 'invalid', 'flavor_profile' => 'invalid', 'sort_order' => '-1', 'is_available' => 'invalid', 'is_featured' => 'invalid'], ['name', 'slug', 'menu_category_id', 'price', 'description', 'image', 'spice_level', 'protein_type', 'dietary_type', 'flavor_profile', 'sort_order', 'is_available', 'is_featured']],
]);

test('menu item removal requires an explicit, described confirmation while preserving the delete form', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $item = MenuItem::factory()->create(['name' => 'Confirmation dish']);

    $response = $this->actingAs($admin)->get(route('admin.menu.items.index'))->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    $form = $xpath->query('//form[@action="'.route('admin.menu.items.destroy', $item).'"]')->item(0);

    expect($form)->not->toBeNull();
    expect($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    expect($xpath->evaluate('string(.//input[@name="_method"]/@value)', $form))->toBe('DELETE');
    expect($xpath->query('.//button[@data-menu-item-remove-open and @type="button"]', $form)->length)->toBe(1);
    expect($xpath->query('.//dialog[@data-menu-item-remove-dialog and @aria-labelledby and @aria-describedby]', $form)->length)->toBe(1);
    expect($xpath->evaluate('string(.//dialog//h2)', $form))->toContain('Confirmation dish');
    expect($xpath->evaluate('string(.//dialog//p)', $form))->toContain('Historical order and reservation records are preserved');
    expect($xpath->query('.//dialog//button[@data-menu-item-remove-cancel and @type="button"]', $form)->length)->toBe(1);
    expect($xpath->query('.//dialog//button[@type="submit"]', $form)->length)->toBe(1);
});

test('report download links name their report and do not claim completed delivery', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());

    expect($xpath->query('//h1')->length)->toBe(1);
    expect($xpath->query('//a[@data-report-download]')->length)->toBe(4);
    foreach (['Weekly Sales', 'Monthly Sales', 'Weekly Reservations', 'Monthly Reservations'] as $name) {
        expect($xpath->query('//a[@data-report-name="'.$name.'" and contains(., "Download '.$name.' PDF")]')->length)->toBe(1);
    }
    expect($xpath->query('//*[@data-report-download-status and @role="status" and @aria-live="polite" and @hidden]')->length)->toBe(1);
    $response->assertSee('this page cannot confirm completion or detect a failed download');
});

test('admin list tables have scoped and named column headers', function (string $routeName, string $modelClass) {
    $admin = User::factory()->create(['role' => 'admin']);
    $modelClass::factory()->create();

    $response = $this->actingAs($admin)->get(route($routeName))->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    $headers = $xpath->query('//table/thead/tr/th');

    expect($headers->length)->toBeGreaterThan(0);
    foreach ($headers as $header) {
        expect($header->getAttribute('scope'))->toBe('col');
        expect(trim($header->textContent))->not->toBeEmpty();
    }
})->with([
    'orders' => ['admin.orders.index', Order::class],
    'reservations' => ['admin.reservations.index', Reservation::class],
    'tables' => ['admin.tables.index', Table::class],
    'menu items' => ['admin.menu.items.index', MenuItem::class],
    'categories' => ['admin.menu.categories.index', MenuCategory::class],
]);

test('order status validation errors describe the status control and retain valid old input', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $order = Order::factory()->create(['status' => 'pending']);
    $showRoute = route('admin.orders.show', $order);

    $this->actingAs($admin)->from($showRoute)->patch(route('admin.orders.update-status', $order), [
        'status' => 'invalid',
        'expected_status' => 'pending',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="status" and @aria-invalid="true" and @aria-describedby="order-status-error"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="order-status-error" and @role="alert"]')->length)->toBe(1);

    $this->from($showRoute)->patch(route('admin.orders.update-status', $order), [
        'status' => 'cancelled',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('expected_status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="status" and not(@aria-invalid)]/option[@value="cancelled" and @selected]')->length)->toBe(1);
    expect($xpath->query('//*[@role="alert" and contains(., "Refresh the page")]')->length)->toBe(1);

    $order->update(['status' => 'completed']);
    $this->from($showRoute)->patch(route('admin.orders.update-status', $order), [
        'status' => 'invalid',
        'expected_status' => 'completed',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="status"]')->length)->toBe(0);
    expect($xpath->query('//*[@id="order-status-error" and @role="alert"]')->length)->toBe(1);
});

test('reservation status errors describe the control and remain visible without a transition form', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $reservation = Reservation::factory()->create(['status' => 'pending']);
    $showRoute = route('admin.reservations.show', $reservation);

    $this->actingAs($admin)->from($showRoute)->patch(route('admin.reservations.update-status', $reservation), [
        'status' => 'invalid',
        'expected_status' => 'pending',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="status" and @aria-invalid="true" and @aria-describedby="reservation-status-error"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="reservation-status-error" and @role="alert"]')->length)->toBe(1);

    $this->from($showRoute)->patch(route('admin.reservations.update-status', $reservation), [
        'status' => 'confirmed',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('expected_status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="status"]/option[@value="confirmed" and @selected]')->length)->toBe(1);

    $reservation->update(['status' => 'cancelled']);
    $this->from($showRoute)->patch(route('admin.reservations.update-status', $reservation), [
        'status' => 'invalid',
        'expected_status' => 'cancelled',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="status"]')->length)->toBe(0);
    expect($xpath->query('//*[@id="reservation-status-error" and @role="alert"]')->length)->toBe(1);
});

test('reservation table assignment errors describe the control or remain in a summary when the form is absent', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $currentTable = Table::factory()->create(['capacity' => 4]);
    $otherTable = Table::factory()->create(['capacity' => 4]);
    $reservation = Reservation::factory()->create([
        'table_id' => $currentTable->id,
        'guest_count' => 2,
        'reservation_date' => now('Asia/Kuala_Lumpur')->addDays(2)->toDateString(),
        'reservation_time' => '10:00',
        'status' => 'confirmed',
    ]);
    $showRoute = route('admin.reservations.show', $reservation);

    $this->actingAs($admin)->from($showRoute)->patch(route('admin.reservations.update-table', $reservation), [
        'table_id' => 'invalid',
        'expected_table_id' => $currentTable->id,
        'expected_status' => 'confirmed',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('table_id');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="table_id" and @aria-invalid="true" and @aria-describedby="reservation-table-error"]')->length)->toBe(1);
    expect($xpath->query('//*[@id="reservation-table-error" and @role="alert"]')->length)->toBe(1);

    $this->from($showRoute)->patch(route('admin.reservations.update-table', $reservation), [
        'table_id' => $otherTable->id,
        'expected_table_id' => $currentTable->id,
    ])->assertRedirect($showRoute)->assertSessionHasErrors('expected_status');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="table_id"]/option[@value="'.$otherTable->id.'" and @selected]')->length)->toBe(1);

    $reservation->update(['status' => 'cancelled']);
    $this->from($showRoute)->patch(route('admin.reservations.update-table', $reservation), [
        'table_id' => 'invalid',
        'expected_table_id' => $currentTable->id,
        'expected_status' => 'cancelled',
    ])->assertRedirect($showRoute)->assertSessionHasErrors('table_id');

    $response = $this->withCookie(config('session.cookie'), session()->getId())->get($showRoute)->assertOk();
    $xpath = adminAccessibilityXPath($response->getContent());
    expect($xpath->query('//select[@name="table_id"]')->length)->toBe(0);
    expect($xpath->query('//*[@id="reservation-table-error" and @role="alert"]')->length)->toBe(1);
});

test('admin row actions identify their records without guest names', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    MenuItem::factory()->create(['name' => 'Accessibility dish']);
    MenuCategory::factory()->create(['name' => 'Accessibility category']);
    $table = Table::factory()->create(['table_number' => 'A15']);
    $order = Order::factory()->create(['customer_name' => 'Private order guest']);
    $reservation = Reservation::factory()->create(['customer_name' => 'Private reservation guest']);

    foreach ([
        ['admin.menu.items.index', '//tr[td[contains(., "Accessibility dish")]]', ['Deactivate menu item Accessibility dish', 'Remove menu item Accessibility dish', 'Edit menu item Accessibility dish']],
        ['admin.menu.categories.index', '//tr[td[contains(., "Accessibility category")]]', ['Edit category Accessibility category']],
        ['admin.tables.index', '//tr[td[contains(., "Table A15")]]', ['Edit table A15']],
        ['admin.orders.index', '//tr[td[contains(., "'.$order->order_number.'")]]', ['View order '.$order->order_number]],
        ['admin.reservations.index', '//tr[td[contains(., "'.$reservation->reservation_number.'")]]', ['View reservation '.$reservation->reservation_number]],
    ] as [$routeName, $rowQuery, $names]) {
        $response = $this->actingAs($admin)->get(route($routeName))->assertOk();
        $xpath = adminAccessibilityXPath($response->getContent());
        $row = $xpath->query($rowQuery)->item(0);
        expect($row, $routeName)->not->toBeNull();

        foreach ($names as $name) {
            expect($xpath->query('.//*[self::a or self::button][contains(normalize-space(.), "'.$name.'")]', $row)->length, $name)->toBe(1);
        }

        if ($routeName === 'admin.orders.index') {
            expect($xpath->evaluate('string(./td[last()])', $row))->not->toContain($order->customer_name);
        }

        if ($routeName === 'admin.reservations.index') {
            expect($xpath->evaluate('string(./td[last()])', $row))->not->toContain($reservation->customer_name);
        }
    }
});
