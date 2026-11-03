<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class StripePaymentService implements PaymentProcessor
{
    private const SESSIONS_URL = 'https://api.stripe.com/v1/checkout/sessions';

    public function __construct(private PaymentApplier $applier, private MockPaymentService $simulator) {}

    public function prepare(Payment $payment): array
    {
        if (trim((string) config('services.stripe.secret')) === '') {
            if (isset($payment->raw_response['session_id'])) {
                return ['url' => route('payment.failed', $payment)];
            }

            $payment->update(['provider' => 'stripe', 'raw_response' => ['simulator' => true]]);

            return $this->simulator->prepare($payment);
        }

        try {
            $this->ensureTestConfiguration($payment);
            $session = $this->client()->asForm()
                ->withHeaders(['Idempotency-Key' => 'checkout-'.$payment->provider_reference])
                ->post(self::SESSIONS_URL, [
                    'mode' => 'payment',
                    'payment_method_types' => ['card'],
                    'currency' => 'myr',
                    'client_reference_id' => $payment->provider_reference,
                    'metadata' => [
                        'order_id' => (string) $payment->order_id,
                        'reservation_id' => (string) $payment->reservation_id,
                        'payment_id' => (string) $payment->id,
                    ],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'myr',
                            'unit_amount' => (int) round((float) $payment->amount * 100),
                            'product_data' => ['name' => $payment->order_id !== null
                                ? 'Le Miting order '.$payment->order->order_number
                                : 'Le Miting reservation '.$payment->reservation_id],
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => url()->route('payment.stripe.return', ['reference' => $payment->provider_reference])
                        .'&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => url()->route('payment.stripe.return', ['reference' => $payment->provider_reference, 'cancelled' => 1]),
                ])->throw()->json();

            if (! is_array($session)
                || ! $this->validSessionId($session['id'] ?? null)
                || ! is_string($session['url'] ?? null)
                || ! str_starts_with($session['url'], 'https://checkout.stripe.com/')) {
                throw new RuntimeException('Invalid Stripe Checkout response.');
            }

            $payment->update(['provider' => 'stripe', 'raw_response' => ['session_id' => $session['id']]]);

            return ['url' => $session['url']];
        } catch (ConnectionException|RequestException|RuntimeException) {
            Log::warning('Stripe Checkout could not be started.');
            $this->applier->applyFailedPayment(['reference' => $payment->provider_reference, 'provider' => 'stripe']);

            return ['url' => route('payment.failed', $payment)];
        }
    }

    public function verify(array $payload): Payment
    {
        $reference = $payload['reference'] ?? null;
        if (! is_string($reference) || $reference === '') {
            throw new InvalidArgumentException('Missing Stripe payment reference.');
        }

        $payment = Payment::where('provider', 'stripe')->where('provider_reference', $reference)->first();
        $sessionId = $payload['session_id'] ?? ($payment?->raw_response['session_id'] ?? null);
        if (! $payment || ! $this->validSessionId($sessionId)
            || ($payment->raw_response['session_id'] ?? null) !== $sessionId) {
            throw new InvalidArgumentException('Unknown Stripe Checkout Session.');
        }

        $this->ensureTestConfiguration($payment);
        $session = $this->client()->get(self::SESSIONS_URL.'/'.$sessionId)->throw()->json();

        if (! is_array($session)
            || ($session['id'] ?? null) !== $sessionId
            || ($session['livemode'] ?? null) !== false
            || ($session['mode'] ?? null) !== 'payment'
            || ($session['client_reference_id'] ?? null) !== $payment->provider_reference
            || ($session['metadata']['payment_id'] ?? null) !== (string) $payment->id
            || ($session['metadata']['order_id'] ?? '') !== (string) $payment->order_id
            || ($session['metadata']['reservation_id'] ?? '') !== (string) $payment->reservation_id
            || ($session['currency'] ?? null) !== 'myr'
            || ($session['amount_total'] ?? null) !== (int) round((float) $payment->amount * 100)) {
            throw new InvalidArgumentException('Stripe Checkout Session does not match this payment.');
        }

        $data = [
            'reference' => $payment->provider_reference,
            'provider' => 'stripe',
            'raw_response' => [
                'session_id' => $sessionId,
                'payment_status' => $session['payment_status'] ?? null,
                'status' => $session['status'] ?? null,
            ],
        ];

        if (($session['payment_status'] ?? null) === 'paid' && ($session['status'] ?? null) === 'complete') {
            return $this->applier->applyVerifiedPayment($data);
        }

        if (($session['status'] ?? null) === 'expired') {
            return $this->applier->applyFailedPayment($data);
        }

        return $payment;
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->acceptJson()->connectTimeout(3)->timeout(10);
    }

    private function ensureTestConfiguration(Payment $payment): void
    {
        if (! str_starts_with((string) config('services.stripe.secret'), 'sk_test_')
            || config('services.stripe.currency') !== 'myr'
            || $payment->currency !== 'MYR') {
            throw new RuntimeException('Stripe test payments require a test key and MYR currency.');
        }
    }

    private function validSessionId(mixed $sessionId): bool
    {
        return is_string($sessionId) && preg_match('/\Acs_test_[A-Za-z0-9_]{1,240}\z/', $sessionId) === 1;
    }
}
