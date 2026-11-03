<?php

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customerShellXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

test('customer navigation exposes the existing guest destinations without admin links', function () {
    $response = $this->get(route('home'));
    $xpath = customerShellXPath($response->getContent());

    foreach (['Main navigation', 'Quick navigation', 'Mobile navigation'] as $navigation) {
        $links = $xpath->query('//nav[@aria-label="'.$navigation.'"]//a/@href');
        $destinations = array_map(fn (DOMAttr $link) => $link->value, iterator_to_array($links));

        $expected = [route('home'), route('menu.index'), route('ai.recommendations'), route('reservation.create'), route('contact.create')];
        if ($navigation !== 'Main navigation') {
            $expected[] = route('cart.index');
        }
        expect($destinations)->toBe($expected);
    }

    expect($xpath->evaluate('string(//header//a[1]/@href)'))->toBe(route('home'));
    expect($xpath->query('//header//a[@href="'.route('cart.index').'"]')->length)->toBe(2);
    expect($xpath->query('//header//a[contains(@href, "/admin")]')->length)->toBe(0);
});

test('menu details identify menu as the current navigation destination', function () {
    $item = MenuItem::factory()->create();

    $response = $this->get(route('menu.show', $item));
    $xpath = customerShellXPath($response->getContent());

    foreach (['Main navigation', 'Quick navigation', 'Mobile navigation'] as $navigation) {
        expect($xpath->evaluate('string(//nav[@aria-label="'.$navigation.'"]//a[@aria-current="page"]/@href)'))
            ->toBe(route('menu.index'));
    }
});

test('navigation shows only the existing order cart quantity', function () {
    $item = MenuItem::factory()->create();
    $reservationItems = [$item->id => ['quantity' => 9]];

    $response = $this->withSession([
        'cart.order.items' => [$item->id => ['quantity' => 3]],
        'cart.reservation.items' => $reservationItems,
    ])->get(route('home'));
    $xpath = customerShellXPath($response->getContent());

    expect($xpath->evaluate('normalize-space(//*[@id="cart-count"])'))->toBe('(3)');
    $response->assertSessionHas('cart.reservation.items', $reservationItems);
});

test('navigation does not invent a count for an empty order cart', function () {
    $response = $this->get(route('home'));
    $xpath = customerShellXPath($response->getContent());

    expect($xpath->evaluate('normalize-space(//*[@id="cart-count"])'))->toBe('');
});

test('the shell provides a focusable skip target and direct navigation alongside the mobile drawer', function () {
    $response = $this->get(route('home'));
    $xpath = customerShellXPath($response->getContent());
    $target = $xpath->evaluate('string((//body/a)[1]/@href)');
    expect($target)->toBe('#main-content');
    expect($xpath->query('//main[@id="main-content"][@tabindex="-1"]')->length)->toBe(1);
    expect($xpath->query('//nav[@aria-label="Quick navigation"]//a')->length)->toBe(6);
    expect($xpath->query('//header//button[@type="button" and @data-nav-open and @aria-controls="customer-nav-dialog" and @aria-expanded="false"]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="customer-nav-dialog"]//button[@type="button" and @data-nav-close]')->length)->toBe(1);
    expect($xpath->query('//dialog[@id="customer-nav-dialog"]//nav[@id="mobile-menu"]')->length)->toBe(1);
});

test('the footer replaces repeated customer navigation with policies and copyright', function () {
    $this->travelTo(now()->setDate(2026, 9, 20));

    $response = $this->get(route('home'));
    $xpath = customerShellXPath($response->getContent());
    $footer = $xpath->evaluate('normalize-space(//footer)');

    expect($footer)->toBe('Lé Miting Le Miting Terms & Conditions Privacy Policy Cancellation & Refund Policy © 2026 Le Miting. Third-party content belongs to its respective owners.');
    foreach (['policies.terms', 'policies.privacy', 'policies.cancellation'] as $policy) {
        expect($xpath->query('//footer//a[@href="'.route($policy).'"]')->length)->toBe(1);
        $this->get(route($policy))->assertOk();
    }
    expect($xpath->query('//footer//a[contains(@href, "/admin") or starts-with(@href, "tel:") or starts-with(@href, "mailto:")]')->length)->toBe(0);
});

test('customer pages use the warm shader shell without inline scripts or event handlers', function (string $routeName) {
    $response = $this->get(route($routeName))->assertOk();
    $xpath = customerShellXPath($response->getContent());

    expect($xpath->query('//body[contains(concat(" ", normalize-space(@class), " "), " lm-culinary-shader-theme ")]')->length)->toBe(1);
    expect($xpath->query('//canvas[@id="lm-culinary-shader"]')->length)->toBe(0);
    expect($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " lm-shader-grain ") and @aria-hidden="true"]')->length)->toBe(1);
    expect($xpath->query('//script[contains(@src, "/js/customer-interactions.js")]')->length)->toBe(1);
    expect($xpath->query('//script[not(@src)]')->length)->toBe(0);
    expect($xpath->query('//@*[starts-with(translate(name(), "ON", "on"), "on")]')->length)->toBe(0);
})->with(['home' => 'home', 'reservation only' => 'reservation.table', 'reservation with food' => 'reservation.table-food']);

test('the customer shell loads both configured fonts and retains assets csrf and page hooks', function () {
    session()->put('_token', 'shell-test-csrf-token');

    $view = $this->blade(<<<'BLADE'
        @extends('layouts.master')
        @section('title', 'Shell test title')
        @section('meta_description', 'Shell test description')
        @section('content')<p>Shell test content</p>@endsection
        @push('scripts')<script data-shell-test>void 0;</script>@endpush
        BLADE);
    $xpath = customerShellXPath((string) $view);
    $headStyles = $xpath->evaluate('string(//head/style)');

    expect($headStyles)->toContain('DM Serif Display', 'Instrument Sans', '@font-face');
    expect($xpath->evaluate('string(//title)'))->toBe('Shell test title');
    expect($xpath->evaluate('string(//meta[@name="description"]/@content)'))->toBe('Shell test description');
    expect($xpath->evaluate('string(//meta[@name="csrf-token"]/@content)'))->toBe('shell-test-csrf-token');
    expect($xpath->evaluate('string(//meta[@name="viewport"]/@content)'))->toBe('width=device-width, initial-scale=1');
    expect($xpath->query('//head/link[@rel="stylesheet"]')->length)->toBeGreaterThan(0);
    expect($xpath->query('//head/script[@type="module"][@src]')->length)->toBeGreaterThan(0);
    expect($xpath->evaluate('normalize-space(//main)'))->toBe('Shell test content');
    expect($xpath->query('//body/script[@data-shell-test]')->length)->toBe(1);
});
