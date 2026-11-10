<?php

use App\Events\OrderPaymentVerified;
use App\Mail\OrderConfirmationMail;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'payment.driver' => 'stripe',
        'services.stripe.secret' => 'sk_test_not_a_real_key',
        'services.stripe.currency' => 'myr',
    ]);
    Http::preventStrayRequests();
});

function stripePendingPayment(): Payment
{
    return Payment::factory()->for(Order::factory()->create(['total' => 42.35]))->create([
        'provider' => 'stripe', 'amount' => 42.35,
        'raw_response' => ['session_id' => 'cs_test_checkout'],
    ]);
}

/** @return array<string, mixed> */
function stripeSession(Payment $payment, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'cs_test_checkout', 'livemode' => false, 'mode' => 'payment',
        'client_reference_id' => $payment->provider_reference,
        'metadata' => [
            'payment_id' => (string) $payment->id,
            'order_id' => (string) $payment->order_id,
            'reservation_id' => (string) $payment->reservation_id,
        ],
        'amount_total' => 4235, 'currency' => 'myr',
        'payment_status' => 'paid', 'status' => 'complete',
    ], $overrides);
}

function stripeReturnUrl(Payment $payment, array $parameters = []): string
{
    return route('payment.stripe.return', [
        'reference' => $payment->provider_reference,
        'session_id' => 'cs_test_checkout',
        ...$parameters,
    ]);
}

function assertStripeCsp(string $html): void
{
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//script[not(@src)]')->length)->toBe(0);
    expect($xpath->query('//@*[starts-with(translate(name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "on")]')->length)->toBe(0);
}

test('a failed checkout retries with the supported Stripe card filter', function () {
    $payment = stripePendingPayment();
    $payment->update(['status' => 'failed', 'raw_response' => null]);
    $originalReference = $payment->provider_reference;
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => function (Request $request) {
        if (array_key_exists('payment_method_types', $request->data())
            || ($request['allowed_payment_method_types'] ?? null) !== ['card']
            || ! $request->hasHeader('Stripe-Version', '2026-10-28.endive')) {
            return Http::response(['error' => [
                'type' => 'invalid_request_error', 'param' => 'payment_method_types',
                'message' => 'The payment_method_types parameter is no longer supported.',
            ]], 400);
        }

        return Http::response(['id' => 'cs_test_checkout', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout']);
    }]);

    $this->post(route('payment.retry', $payment))->assertRedirect(route('payment.create', $payment));
    $this->get(route('payment.create', $payment))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    expect($payment->refresh()->provider_reference)->not->toBe($originalReference);
    expect($payment->raw_response)->toBe(['session_id' => 'cs_test_checkout']);
    expect(Order::count())->toBe(1);
    expect(Payment::count())->toBe(1);
    Http::assertSentCount(1);
});

test('Stripe checkout completes an order on local HTTP and public HTTPS', function (string $origin, array $headers, string $returnOrigin) {
    Mail::fake();
    $item = MenuItem::factory()->create(['price' => 12.35]);
    $createdPayload = [];
    Http::fake([
        'https://api.stripe.com/v1/checkout/sessions' => function (Request $request) use (&$createdPayload) {
            $createdPayload = $request->data();
            if (array_key_exists('payment_method_types', $createdPayload)
                || ($createdPayload['allowed_payment_method_types'] ?? null) !== ['card']) {
                return Http::response(['error' => ['param' => 'payment_method_types']], 400);
            }

            return Http::response(['id' => 'cs_test_checkout', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout']);
        },
        'https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => function (Request $request) {
            expect($request->hasHeader('Stripe-Version', '2026-10-28.endive'))->toBeTrue();

            return Http::response(stripeSession(Payment::firstOrFail(), ['amount_total' => 2470]));
        },
    ]);

    $this->withHeaders($headers)->withSession(['cart.order.items' => [
        $item->id => ['menu_item_id' => $item->id, 'quantity' => 2],
    ]])->post($origin.'/checkout', [
        'customer_name' => 'Stripe Test Customer', 'customer_email' => 'stripe-test@example.invalid',
        'customer_phone' => '0123456789', 'order_type' => 'takeaway',
    ])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');

    $payment = Payment::firstOrFail();
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending', 'amount' => '24.70']);
    expect($createdPayload['success_url'])->toBe($returnOrigin.'/payment/stripe/return?reference='.$payment->provider_reference.'&session_id={CHECKOUT_SESSION_ID}');
    expect($createdPayload['cancel_url'])->toBe($returnOrigin.'/payment/stripe/return?reference='.$payment->provider_reference.'&cancelled=1');

    $return = $this->get(str_replace('{CHECKOUT_SESSION_ID}', 'cs_test_checkout', $createdPayload['success_url']))
        ->assertRedirectContains('/orders/'.$payment->order_id.'/confirmation')
        ->assertSessionMissing('cart.order.items');
    $confirmation = $this->get($return->headers->get('Location'))->assertOk()->assertSee($payment->order->order_number);

    assertStripeCsp($confirmation->getContent());
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'confirmed']);
    Mail::assertSent(OrderConfirmationMail::class, 1);
    Http::assertSentCount(2);
})->with([
    'local HTTP' => ['http://fyp.test', [], 'http://fyp.test'],
    'public HTTPS' => ['https://lemiting.me', [], 'https://lemiting.me'],
    'Cloudflare forwarded HTTPS' => ['http://fyp.test', [
        'X-Forwarded-Host' => 'lemiting.me', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443',
    ], 'https://lemiting.me'],
]);

test('customer checkout creates a MYR Stripe session in sen and redirects to the hosted URL', function () {
    $item = MenuItem::factory()->create(['price' => 12.35]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response([
        'id' => 'cs_test_checkout', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout',
    ])]);

    $this->withSession(['cart.order.items' => [
        $item->id => ['menu_item_id' => $item->id, 'quantity' => 2],
    ]])->post(route('checkout.store'), [
        'customer_name' => 'Aida', 'customer_email' => 'aida@example.com',
        'customer_phone' => '0123456789', 'order_type' => 'takeaway', 'amount' => 0.01,
    ])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');

    $payment = Payment::firstOrFail();
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'provider' => 'stripe', 'status' => 'pending', 'amount' => '24.70']);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'pending', 'total' => '24.70']);
    expect($payment->raw_response)->toBe(['session_id' => 'cs_test_checkout']);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
        && $request->hasHeader('Authorization', 'Bearer sk_test_not_a_real_key')
        && $request->hasHeader('Content-Type', 'application/x-www-form-urlencoded')
        && $request->hasHeader('Idempotency-Key', 'checkout-'.$payment->provider_reference)
        && $request->hasHeader('Stripe-Version', '2026-10-28.endive')
        && $request['mode'] === 'payment' && $request['currency'] === 'myr'
        && $request['allowed_payment_method_types'] === ['card']
        && ! array_key_exists('payment_method_types', $request->data())
        && $request['line_items'][0]['price_data']['unit_amount'] === 2470
        && $request['line_items'][0]['price_data']['currency'] === 'myr'
        && $request['client_reference_id'] === $payment->provider_reference
        && $request['metadata']['order_id'] === (string) $payment->order_id
        && $request['metadata']['payment_id'] === (string) $payment->id
        && str_contains($request['success_url'], 'session_id={CHECKOUT_SESSION_ID}')
        && str_contains($request['cancel_url'], 'cancelled=1'));
});

test('Stripe redirects return to the current local or tunnel origin', function (string $requestOrigin, array $headers, string $expectedOrigin) {
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response([
        'id' => 'cs_test_checkout', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_checkout',
    ])]);

    $this->withHeaders($headers)->get($requestOrigin.'/payment/'.$payment->id)
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_checkout');

    Http::assertSent(fn (Request $request): bool => $request['success_url']
        === $expectedOrigin.'/payment/stripe/return?reference='.$payment->provider_reference.'&session_id={CHECKOUT_SESSION_ID}'
        && $request['cancel_url']
        === $expectedOrigin.'/payment/stripe/return?reference='.$payment->provider_reference.'&cancelled=1');
})->with([
    'local HTTP' => ['http://fyp.test', [], 'http://fyp.test'],
    'public HTTPS' => ['https://lemiting.me', [], 'https://lemiting.me'],
    'Cloudflare forwarded HTTPS' => ['http://fyp.test', [
        'X-Forwarded-Host' => 'lemiting.me',
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Port' => '443',
    ], 'https://lemiting.me'],
]);

test('Stripe returns are forbidden when a legacy driver is selected', function (string $driver) {
    config(['payment.driver' => $driver]);
    $payment = stripePendingPayment();

    $this->get(stripeReturnUrl($payment))->assertForbidden();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending', 'paid_at' => null]);
    Http::assertNothingSent();
})->with(['mock', 'fiuu']);

test('a verified paid Stripe return confirms the order clears its cart and sends one confirmation', function () {
    Mail::fake();
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response(stripeSession($payment))]);

    $response = $this->withSession(['cart.order.items' => [1 => ['menu_item_id' => 1, 'quantity' => 1]]])
        ->get(stripeReturnUrl($payment));

    $response->assertRedirectContains('/orders/'.$payment->order_id.'/confirmation');
    $response->assertSessionMissing('cart.order.items');
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'provider' => 'stripe', 'status' => 'paid']);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'confirmed']);
    expect($payment->refresh()->paid_at)->not->toBeNull();
    expect(URL::hasValidSignature(Illuminate\Http\Request::create($response->headers->get('Location'))))->toBeTrue();
    Mail::assertSent(OrderConfirmationMail::class, 1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://api.stripe.com/v1/checkout/sessions/cs_test_checkout');
});

test('unpaid cancelled and expired Stripe sessions do not confirm an order', function (string $status, bool $cancelled, string $expectedStatus) {
    Event::fake([OrderPaymentVerified::class]);
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response(stripeSession($payment, [
        'payment_status' => 'unpaid', 'status' => $status,
    ]))]);

    $response = $this->get(stripeReturnUrl($payment, $cancelled ? ['session_id' => null, 'cancelled' => 1] : []));

    if ($cancelled || $expectedStatus === 'failed') {
        $response->assertRedirect(route('payment.failed', $payment));
    } else {
        $response->assertOk()->assertSee('Verifying your payment');
    }
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => $expectedStatus, 'paid_at' => null]);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'pending']);
    Event::assertNotDispatched(OrderPaymentVerified::class);
})->with([
    'unpaid return' => ['open', false, 'pending'],
    'cancelled browser return' => ['open', true, 'pending'],
    'expired checkout' => ['expired', false, 'failed'],
    'unsettled complete checkout' => ['complete', false, 'pending'],
]);

test('a cancellation flag cannot override a paid Stripe session', function () {
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response(stripeSession($payment))]);

    $this->get(stripeReturnUrl($payment, ['cancelled' => 1]))
        ->assertRedirectContains('/orders/'.$payment->order_id.'/confirmation');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
});

test('duplicate Stripe callbacks and returns apply payment once without regressing the order', function () {
    Event::fake([OrderPaymentVerified::class]);
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response(stripeSession($payment))]);
    $payload = ['reference' => $payment->provider_reference, 'session_id' => 'cs_test_checkout'];

    $this->post(route('payment.callback'), $payload)->assertOk()->assertExactJson(['status' => 'OK']);
    $firstPaidAt = $payment->refresh()->paid_at;
    $payment->order->update(['status' => 'preparing']);
    $this->post(route('payment.callback'), $payload)->assertOk()->assertExactJson(['status' => 'OK']);
    $this->get(stripeReturnUrl($payment))->assertRedirectContains('/orders/'.$payment->order_id.'/confirmation');

    expect($payment->refresh()->paid_at->equalTo($firstPaidAt))->toBeTrue();
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'preparing']);
    Event::assertDispatchedTimes(OrderPaymentVerified::class, 1);
});

test('Stripe rejects mismatched sessions without applying payment or exposing provider data', function (array $overrides) {
    Event::fake([OrderPaymentVerified::class]);
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response(stripeSession($payment, $overrides))]);

    $this->get(stripeReturnUrl($payment))->assertRedirect(route('payment.failed', $payment))
        ->assertSessionHasErrors('payment');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending', 'paid_at' => null]);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'pending']);
    Event::assertNotDispatched(OrderPaymentVerified::class);
})->with([
    'amount' => [['amount_total' => 1]],
    'currency' => [['currency' => 'usd']],
    'session ID' => [['id' => 'cs_test_other']],
    'reference' => [['client_reference_id' => 'other-payment']],
    'payment metadata' => [['metadata' => ['payment_id' => '999999']]],
    'order metadata' => [['metadata' => ['order_id' => '999999']]],
    'reservation metadata' => [['metadata' => ['reservation_id' => '999999']]],
    'live mode' => [['livemode' => true]],
    'wrong mode' => [['mode' => 'subscription']],
]);

test('an unbound session ID or a forged paid browser status cannot change a payment', function () {
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/*' => Http::response([], 500)]);

    $this->get(stripeReturnUrl($payment, ['session_id' => 'cs_test_unbound', 'status' => 'paid']))
        ->assertRedirect(route('payment.failed', $payment))->assertSessionHasErrors('payment');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    Http::assertNothingSent();
});

test('Stripe return validates session IDs before contacting the provider', function (string $sessionId) {
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/*' => Http::response([], 500)]);

    $this->get(stripeReturnUrl($payment, ['session_id' => $sessionId]))->assertSessionHasErrors('session_id');

    Http::assertNothingSent();
})->with(['', '../other', 'cs_live_other']);

test('Stripe verification errors leave the pending transaction recoverable', function (string $failure) {
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => match ($failure) {
        'network' => Http::failedConnection(),
        'malformed' => Http::response('not-json'),
        default => Http::response(['error' => ['message' => 'sensitive-provider-detail']], 401),
    }]);

    $this->get(stripeReturnUrl($payment))->assertRedirect(route('payment.failed', $payment))->assertSessionHasErrors('payment');
    $response = $this->get(route('payment.failed', $payment))->assertOk()->assertSee('Return to Stripe Checkout')
        ->assertDontSee('sensitive-provider-detail')->assertDontSee('sk_test_not_a_real_key');

    assertStripeCsp($response->getContent());
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
})->with(['network', '401', 'malformed']);

test('Stripe session creation failures show recovery rather than silently simulating a charge', function (string $failure) {
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => match ($failure) {
        'network' => Http::failedConnection(),
        'untrusted URL' => Http::response(['id' => 'cs_test_checkout', 'url' => 'https://example.com/pay']),
        'malformed' => Http::response('not-json'),
        default => Http::response(['error' => ['message' => 'sensitive-provider-detail']], 401),
    }]);

    $this->get(route('payment.create', $payment))->assertRedirect(route('payment.failed', $payment));

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'provider' => 'stripe', 'status' => 'failed']);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'pending']);
})->with(['network', '401', 'malformed', 'untrusted URL']);

test('Stripe creation diagnostics omit credentials and untrusted provider details', function (string $parameter, ?string $expectedParameter) {
    Log::spy();
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response([
        'error' => [
            'type' => 'invalid_request_error',
            'param' => $parameter,
            'message' => 'Sensitive provider detail containing sk_test_not_a_real_key',
        ],
    ], 400)]);

    $this->get(route('payment.create', $payment))->assertRedirect(route('payment.failed', $payment));

    Log::shouldHaveReceived('warning')->once()->with('Stripe Checkout could not be started.', [
        'payment_id' => $payment->id,
        'failure_type' => RequestException::class,
        'http_status' => 400,
        'stripe_parameter' => $expectedParameter,
    ]);
    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'pending']);
    Http::assertSentCount(1);
})->with([
    'known parameter' => ['payment_method_types', 'payment_method_types'],
    'untrusted parameter' => ['sk_test_not_a_real_key', null],
]);

test('Stripe test checkout rejects live keys and unsupported currencies before an HTTP request', function (string $secret, string $currency) {
    $payment = stripePendingPayment();
    config(['services.stripe.secret' => $secret, 'services.stripe.currency' => $currency]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response([], 500)]);

    $this->get(route('payment.create', $payment))->assertRedirect(route('payment.failed', $payment));

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
    Http::assertNothingSent();
})->with([
    'live key' => ['sk_live_not_a_real_key', 'myr'],
    'unsupported currency' => ['sk_test_not_a_real_key', 'usd'],
]);

test('blank Stripe credentials provide a signed interactive simulator with safe outcomes', function (string $outcome, string $paymentStatus, string $orderStatus) {
    config(['services.stripe.secret' => '']);
    $payment = stripePendingPayment();
    $payment->update(['raw_response' => null]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => Http::response([], 500)]);

    $redirect = $this->get(route('payment.create', $payment))->assertRedirect();
    $gateway = $this->get($redirect->headers->get('Location'))->assertOk()
        ->assertSee('Stripe Checkout (MYR)')->assertSee('Test simulator')
        ->assertSee('4242 4242 4242 4242')->assertDontSee('Fiuu');
    assertStripeCsp($gateway->getContent());
    $processUrl = URL::temporarySignedRoute('mock-payment.process', now()->addMinutes(15), ['payment' => $payment]);
    $this->post($processUrl, ['payment_method' => 'card', 'outcome' => $outcome])
        ->assertRedirect(route('payment.return', ['reference' => $payment->provider_reference]));

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'provider' => 'stripe', 'status' => $paymentStatus]);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => $orderStatus]);
    Http::assertNothingSent();
})->with([
    'success' => ['success', 'paid', 'confirmed'],
    'decline' => ['failure', 'failed', 'pending'],
    'cancel' => ['abandoned', 'pending', 'pending'],
]);

test('a real Stripe payment cannot be completed through the simulator even with a valid signed link', function () {
    $payment = stripePendingPayment();
    $url = URL::temporarySignedRoute('mock-payment.process', now()->addMinutes(15), ['payment' => $payment]);

    $this->post($url, ['payment_method' => 'card', 'outcome' => 'success'])->assertNotFound();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
});

test('removing a Stripe key cannot convert an existing hosted session into a simulated payment', function () {
    config(['services.stripe.secret' => '']);
    $payment = stripePendingPayment();
    Http::fake(['https://api.stripe.com/v1/checkout/sessions*' => Http::response([], 500)]);

    $this->get(route('payment.create', $payment))->assertRedirect(route('payment.failed', $payment));
    $this->get(URL::temporarySignedRoute('mock-payment.show', now()->addMinutes(15), ['payment' => $payment]))->assertNotFound();

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    expect($payment->refresh()->raw_response)->toBe(['session_id' => 'cs_test_checkout']);
    Http::assertNothingSent();
});

test('a retried payment cannot be settled by the previous Stripe session', function () {
    $payment = stripePendingPayment();
    $oldSession = stripeSession($payment);
    $payment->update(['status' => 'failed']);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response($oldSession)]);

    $this->post(route('payment.retry', $payment))->assertRedirect(route('payment.create', $payment));
    $payment->refresh();
    $this->get(stripeReturnUrl($payment))->assertRedirect(route('payment.failed', $payment))->assertSessionHasErrors('payment');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'pending']);
    $this->assertDatabaseHas('orders', ['id' => $payment->order_id, 'status' => 'pending']);
});

test('Fiuu branding is visible only when the legacy driver is explicitly selected', function () {
    config(['payment.driver' => 'fiuu']);

    $this->get(route('policies.terms'))->assertOk()->assertSee('Fiuu')->assertDontSee('Stripe Checkout');
});

test('Stripe also verifies and confirms a paid reservation without changing the order pipeline', function () {
    $reservation = Reservation::factory()->withFood()->create([
        'table_id' => Table::factory()->create(['capacity' => 4])->id, 'guest_count' => 2,
    ]);
    $payment = Payment::factory()->forReservation($reservation)->create([
        'provider' => 'stripe', 'raw_response' => ['session_id' => 'cs_test_checkout'],
    ]);
    Http::fake(['https://api.stripe.com/v1/checkout/sessions/cs_test_checkout' => Http::response(stripeSession($payment, [
        'amount_total' => (int) round((float) $payment->amount * 100),
    ]))]);

    $this->get(stripeReturnUrl($payment))->assertRedirectContains('/reservation/'.$reservation->id.'/confirmation');

    $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'provider' => 'stripe']);
    $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
});

test('customer checkout and policies show Stripe branding and comply with strict CSP', function () {
    $item = MenuItem::factory()->create();
    $checkout = $this->withSession(['cart.order.items' => [
        $item->id => ['menu_item_id' => $item->id, 'quantity' => 1],
    ]])->get(route('checkout.create'))->assertOk()->assertSee('Stripe Checkout (MYR)')
        ->assertSee('4242 4242 4242 4242')->assertDontSee('Fiuu');
    assertStripeCsp($checkout->getContent());

    foreach (['policies.terms', 'policies.privacy', 'policies.cancellation'] as $route) {
        $policy = $this->get(route($route))->assertOk()->assertSee('Stripe')->assertDontSee('Fiuu');
        assertStripeCsp($policy->getContent());
    }
});
