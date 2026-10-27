<?php

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function transactionXPath(string $html): DOMXPath
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($document);
}

/** @return array<string, mixed> */
function transactionCart(MenuItem $item, int $quantity = 2): array
{
    return ['cart.order.items' => [
        $item->id => ['menu_item_id' => $item->id, 'quantity' => $quantity],
    ]];
}

test('cart exposes the existing update remove and clear form contracts with current prices', function () {
    $item = MenuItem::factory()->create(['price' => 12.50]);
    $response = $this->withSession(transactionCart($item))->get(route('cart.index'));
    $response->assertOk()->assertSee($item->name)->assertSee('RM 12.50 each')->assertSee('RM 25.00');
    $xpath = transactionXPath($response->getContent());
    foreach (['cart.update' => 'PATCH', 'cart.remove' => 'DELETE', 'cart.clear' => 'DELETE'] as $route => $method) {
        $action = $route === 'cart.clear' ? route($route) : route($route, $item->id);
        $form = $xpath->query('//main//form[@action="'.$action.'"][.//input[@name="_method" and @value="'.$method.'"]]')->item(0);
        expect($form)->not->toBeNull();
        expect($form->getAttribute('method'))->toBe('POST')
            ->and($xpath->query('.//input[@name="_method"]', $form)->item(0)->getAttribute('value'))->toBe($method)
            ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    }
    expect($xpath->query('//select[@name="quantity"]/option')->length)->toBe(10)
        ->and($xpath->query('//select[@name="quantity"]/option[@selected]')->item(0)->getAttribute('value'))->toBe('2')
        ->and($xpath->query('//label[@for="quantity-'.$item->id.'"]')->length)->toBe(1)
        ->and($xpath->query('//main//a[@href="'.route('checkout.create').'"]')->length)->toBe(1)
        ->and($xpath->query('//main//img')->length)->toBe(0);
});

test('unavailable and empty carts offer recovery without a checkout action', function (bool $empty) {
    if (! $empty) {
        $item = MenuItem::factory()->unavailable()->create();
        $this->withSession(transactionCart($item));
    }
    $response = $this->get(route('cart.index'))->assertOk();
    $response->assertSee($empty ? 'Your order is empty.' : 'Remove unavailable items before checking out.')
        ->assertSee('href="'.route('menu.index').'"', false);
    expect(transactionXPath($response->getContent())->query('//main//a[@href="'.route('checkout.create').'"]')->length)->toBe(0);
})->with([true, false]);

test('checkout presents the existing fields canonical order types and exposed total', function () {
    $item = MenuItem::factory()->create(['price' => 12.50]);
    $response = $this->withSession(transactionCart($item))->get(route('checkout.create'));
    $response->assertOk()->assertSee('RM 25.00')->assertSee('Proceed to Payment')
        ->assertDontSee('Simulate')->assertDontSee('mock')->assertDontSee('Subtotal');
    $xpath = transactionXPath($response->getContent());
    $form = $xpath->query('//main//form')->item(0);
    expect($form->getAttribute('action'))->toBe(route('checkout.store'))
        ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
    foreach (['customer_name', 'customer_email', 'customer_phone', 'notes'] as $field) {
        expect($xpath->query('.//label[@for="'.$field.'"]', $form)->length)->toBe(1)
            ->and($xpath->query('.//*[@name="'.$field.'"]', $form)->length)->toBe(1);
    }
    $types = $xpath->query('.//input[@name="order_type"]', $form);
    expect(array_map(fn (DOMElement $input) => $input->getAttribute('value'), iterator_to_array($types)))->toBe(['dine-in', 'takeaway']);
    expect($xpath->query('.//fieldset/legend', $form)->length)->toBe(1)
        ->and($xpath->query('.//input[@name="total" or @name="subtotal" or @name="amount"]', $form)->length)->toBe(0);
});

test('checkout validation retains input and associates all field errors', function () {
    $item = MenuItem::factory()->create();
    $fields = ['customer_name', 'customer_email', 'customer_phone', 'order_type', 'notes'];
    $this->withSession(transactionCart($item))->from(route('checkout.create'))->post(route('checkout.store'), [
        'customer_name' => '', 'customer_email' => 'invalid-email', 'customer_phone' => '',
        'order_type' => 'invalid', 'notes' => str_repeat('x', 1001),
    ])->assertSessionHasErrors($fields);
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('checkout.create'))->assertOk();
    $xpath = transactionXPath($response->getContent());
    foreach ($fields as $field) {
        $control = $xpath->query('//*[@name="'.$field.'"]')->item(0);
        expect($control->getAttribute('aria-invalid'))->toBe('true');
        expect($xpath->query('//*[@id="'.$control->getAttribute('aria-describedby').'"]')->length)->toBe(1);
    }
    expect($xpath->query('//input[@name="customer_email"]')->item(0)->getAttribute('value'))->toBe('invalid-email');
});

test('contact and Rasa keep their forms on separate pages without demo disclosures', function () {
    config(['restaurant.is_demo' => true]);
    $response = $this->get(route('contact.create'))->assertOk()
        ->assertDontSee('No. 12, Jalan Melaka Raya')->assertDontSee('hello@lemiting.my')
        ->assertDontSee('+60 6-123 4567')->assertSee('Opening hours')
        ->assertDontSee('academic demonstration')->assertDontSee('not an operating restaurant')
        ->assertDontSee('academic prototype');
    $xpath = transactionXPath($response->getContent());
    expect($xpath->query('//main//form')->length)->toBe(1)
        ->and($xpath->query('//form[@id="rasa-ai-form"]')->length)->toBe(0)
        ->and($xpath->query('//main//form//form')->length)->toBe(0);
    $contact = $xpath->query('//form[@id="contact-form"]')->item(0);
    $rasaPage = transactionXPath($this->get(route('ai.recommendations'))->assertOk()->getContent());
    $rasa = $rasaPage->query('//form[@id="rasa-ai-form"]')->item(0);
    expect($contact->getAttribute('action'))->toBe(route('contact.store'))
        ->and($rasa->getAttribute('action'))->toBe(route('ai.recommendations.recommend'));
    foreach (['name', 'email', 'message'] as $field) {
        expect($xpath->query('.//*[@name="'.$field.'"]', $contact)->length)->toBe(1)
            ->and($rasaPage->query('.//*[@name="'.$field.'"]', $rasa)->length)->toBe(0);
    }
    expect($rasaPage->query('.//textarea[@name="preferences_text"]', $rasa)->length)->toBe(1)
        ->and($rasaPage->query('.//input[@type="checkbox" and @name="excluded_allergens[]"]', $rasa)->length)->toBe(10);
});

test('contact validation associates errors while preserving the enquiry text', function () {
    $this->from(route('contact.create'))->post(route('contact.store'), [
        'name' => '', 'email' => 'invalid', 'message' => str_repeat('x', 2001),
    ])->assertSessionHasErrors(['name', 'email', 'message']);
    $response = $this->withCookie(config('session.cookie'), session()->getId())->get(route('contact.create'))->assertOk();
    $xpath = transactionXPath($response->getContent());
    foreach (['name', 'email', 'message'] as $field) {
        $control = $xpath->query('//*[@id="'.$field.'"]')->item(0);
        expect($control->getAttribute('aria-invalid'))->toBe('true')
            ->and($xpath->query('//*[@id="'.$control->getAttribute('aria-describedby').'"]')->length)->toBe(1);
    }
    expect($xpath->query('//textarea[@id="message"]')->item(0)->textContent)->toBe(str_repeat('x', 2001));
});

test('mock gateway preserves signed actions and all three demo outcomes', function () {
    config(['payment.driver' => 'mock']);
    $payment = Payment::factory()->create(['amount' => 25.00]);
    $url = URL::temporarySignedRoute('mock-payment.show', now()->addMinutes(15), ['payment' => $payment]);
    $response = $this->get($url)->assertOk()->assertSee('Payment Gateway')
        ->assertSee('Online Payment Verification')->assertDontSee('Demo')->assertDontSee('Simulated');
    $xpath = transactionXPath($response->getContent());
    $forms = $xpath->query('//main//form');
    expect($forms->length)->toBe(3);
    $outcomes = [];
    foreach ($forms as $form) {
        $action = $form->getAttribute('action');
        expect(URL::hasValidSignature(Request::create($action, 'POST')))->toBeTrue()
            ->and(parse_url($action, PHP_URL_PATH))->toBe('/mock-payment/'.$payment->id)
            ->and($xpath->query('.//input[@name="_token"]', $form)->length)->toBe(1);
        $outcomes[] = $xpath->query('.//input[@name="outcome"]', $form)->item(0)->getAttribute('value');
    }
    expect($outcomes)->toBe(['success', 'abandoned', 'failure']);
    $methods = $xpath->query('//input[@type="radio"]');
    expect(array_map(fn (DOMElement $input) => $input->getAttribute('value'), iterator_to_array($methods)))->toBe(['online_banking', 'card', 'ewallet']);
});

test('payment failure displays only the retry action allowed by the controller', function (bool $retryable) {
    $payment = Payment::factory()->create(['status' => 'failed', 'amount' => 25.00]);
    $payment->order->update(['total' => 25.00, 'status' => $retryable ? 'pending' : 'cancelled']);
    $response = $this->get(route('payment.failed', $payment))->assertOk()
        ->assertSee('Payment not completed')->assertDontSee('No payment was deducted');
    $xpath = transactionXPath($response->getContent());
    expect($xpath->query('//form[@action="'.route('payment.retry', $payment).'"]')->length)->toBe($retryable ? 1 : 0);
})->with([true, false]);

test('pending payment return states retain the actual reference without implying success', function () {
    $payment = Payment::factory()->create();
    $this->get(route('payment.return', ['reference' => $payment->provider_reference]))->assertOk()
        ->assertSee('Awaiting verification')->assertSee($payment->provider_reference)
        ->assertSee('Returning to this page does not mean your payment succeeded.')
        ->assertDontSee('will appear here once')->assertDontSee('animate-spin');
    expect($payment->fresh()->status)->toBe('pending');
});

test('confirmation renders stored order data and a paid receipt link with a 24 hour expiry', function () {
    $this->freezeSecond();
    $order = Order::factory()->paid()->create(['total' => 25.00, 'order_type' => 'dine-in', 'notes' => 'Existing order note']);
    $menuItem = MenuItem::factory()->create(['name' => 'Changed menu name', 'price' => 90.00]);
    $order->orderItems()->create(['menu_item_id' => $menuItem->id, 'item_name' => 'Original order item', 'unit_price' => 12.50, 'quantity' => 2]);
    $payment = Payment::factory()->paid()->create(['order_id' => $order->id, 'amount' => 25.00]);
    $url = URL::temporarySignedRoute('orders.confirmation', now()->addHours(24), ['order' => $order]);
    $response = $this->get($url)->assertOk()->assertSee($order->order_number)
        ->assertSee($order->customer_email)->assertSee('Dine-in')->assertSee('Original order item')
        ->assertSee('RM 25.00')->assertSee($payment->provider_reference)->assertSee('Paid')
        ->assertDontSee('Changed menu name')->assertDontSee('via FIUU')->assertDontSee('has been sent');
    $xpath = transactionXPath($response->getContent());
    $receiptUrl = $xpath->query('//main//a[contains(@href, "/receipt?")]')->item(0)->getAttribute('href');
    parse_str(parse_url($receiptUrl, PHP_URL_QUERY), $query);
    expect(URL::hasValidSignature(Request::create($receiptUrl)))->toBeTrue()
        ->and((int) $query['expires'])->toBe(now()->addHours(24)->timestamp);
    $this->get($receiptUrl)->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get($receiptUrl.'&tampered=1')->assertForbidden()->assertViewIs('errors.invalid-link');
    $this->travel(24)->hours();
    $this->travel(1)->seconds();
    $this->get($receiptUrl)->assertForbidden()->assertViewIs('errors.invalid-link');
});

test('confirmation does not claim a paid payment or offer a receipt when payment is not paid', function (?string $status) {
    $order = Order::factory()->paid()->create();
    if ($status !== null) {
        Payment::factory()->create(['order_id' => $order->id, 'status' => $status]);
    }
    $url = URL::temporarySignedRoute('orders.confirmation', now()->addHours(24), ['order' => $order]);
    $response = $this->get($url)->assertOk()
        ->assertSee($status === null ? 'Payment information is not available.' : ucfirst($status))
        ->assertDontSee('Download receipt (PDF)');
    expect(transactionXPath($response->getContent())->query('//main//a[contains(@href, "/receipt?")]')->length)->toBe(0);
})->with([null, 'pending', 'failed']);

test('a non retryable reservation payment returns to reservations rather than the order cart', function () {
    $reservation = Reservation::factory()->withFood()->create(['status' => 'cancelled']);
    $payment = Payment::factory()->forReservation($reservation)->create(['status' => 'failed']);

    $response = $this->get(route('payment.failed', $payment))->assertOk();
    $xpath = transactionXPath($response->getContent());

    expect($xpath->query('//main//a[@href="'.route('reservation.create').'"]')->length)->toBe(1)
        ->and($xpath->query('//main//a[@href="'.route('cart.index').'"]')->length)->toBe(0)
        ->and($xpath->query('//main//form')->length)->toBe(0);
    expect($payment->fresh()->status)->toBe('failed');
});

test('a paid record on the payment recovery page is not described as an unsuccessful payment', function () {
    $payment = Payment::factory()->paid()->create();

    $response = $this->get(route('payment.failed', $payment))->assertOk()
        ->assertSee('Payment already verified')->assertDontSee('Payment not completed')
        ->assertDontSee('Payment has not been confirmed');

    expect(transactionXPath($response->getContent())->query('//main//form')->length)->toBe(0);
    expect($payment->fresh()->status)->toBe('paid');
});

test('cart displays its accumulated quantity without changing the update contract', function () {
    $item = MenuItem::factory()->create(['price' => 12.50]);
    $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 6])->assertRedirect();
    $this->post(route('cart.add'), ['menu_item_id' => $item->id, 'quantity' => 6])->assertRedirect();

    $response = $this->get(route('cart.index'))->assertOk()->assertSee('RM 150.00');
    $xpath = transactionXPath($response->getContent());

    expect($xpath->evaluate('string(//select[@id="quantity-'.$item->id.'"]/option[@selected]/@value)'))->toBe('12')
        ->and($xpath->query('//select[@id="quantity-'.$item->id.'"]/option[@selected and @disabled]')->length)->toBe(1)
        ->and($xpath->query('//select[@id="quantity-'.$item->id.'"]/option[not(@disabled)]')->length)->toBe(10);
    $response->assertSessionHas('cart.order.items.'.$item->id.'.quantity', 12);
});
