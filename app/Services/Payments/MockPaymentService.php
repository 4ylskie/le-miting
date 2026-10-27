<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

class MockPaymentService implements PaymentProcessor
{
    public function prepare(Payment $payment): array
    {
        return ['url' => URL::temporarySignedRoute('mock-payment.show', now()->addMinutes((int) config('payment.hold_minutes')), ['payment' => $payment])];
    }

    public function processDemoOutcome(Payment $payment, string $outcome): Payment
    {
        return $this->verify($this->callbackPayload($payment, $outcome));
    }

    /**
     * Build a mock-provider callback server-side. This signature is deliberately
     * unrelated to FIUU and must never be sent to the browser.
     *
     * @return array<string, string>
     */
    public function callbackPayload(Payment $payment, string $outcome): array
    {
        $status = match ($outcome) {
            'success' => 'paid',
            'failure' => 'failed',
        };

        $payload = [
            'reference' => $payment->provider_reference,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'status' => $status,
            'currency' => $payment->currency,
        ];

        $payload['signature'] = $this->signature($payload);

        return $payload;
    }

    public function verify(array $payload): Payment
    {
        if (! $this->signatureIsValid($payload)) {
            throw new \InvalidArgumentException('Invalid payment signature.');
        }

        $reference = $payload['reference'];
        $payment = Payment::where('provider_reference', $reference)->first();

        if (! $payment) {
            throw new \InvalidArgumentException('Unknown payment reference.');
        }

        if (isset($payload['amount']) && abs((float) $payload['amount'] - (float) $payment->amount) > 0.01) {
            $applier = app(PaymentApplier::class);

            return $applier->applyFailedPayment([
                ...$payload,
                'raw_response' => $this->callbackAuditData($payload),
            ]);
        }

        if (($payload['status'] ?? null) === 'paid') {
            return app(PaymentApplier::class)->applyVerifiedPayment([
                ...$payload,
                'raw_response' => $this->callbackAuditData($payload),
            ]);
        }

        return app(PaymentApplier::class)->applyFailedPayment([
            ...$payload,
            'raw_response' => $this->callbackAuditData($payload),
        ]);
    }

    private function signatureIsValid(array $payload): bool
    {
        $reference = $payload['reference'] ?? '';
        $amount = $payload['amount'] ?? '';
        $status = $payload['status'] ?? '';
        $currency = $payload['currency'] ?? 'MYR';

        $expected = $this->signature([
            'reference' => (string) $reference,
            'amount' => (string) $amount,
            'status' => (string) $status,
            'currency' => (string) $currency,
        ]);

        return hash_equals($expected, (string) ($payload['signature'] ?? ''));
    }

    /**
     * @param  array{reference: string, amount: string, status: string, currency: string}  $payload
     */
    private function signature(array $payload): string
    {
        return hash_hmac(
            'sha256',
            $payload['reference'].$payload['amount'].$payload['status'].$payload['currency'],
            $this->mockSigningKey(),
        );
    }

    private function mockSigningKey(): string
    {
        return hash_hmac('sha256', 'le-miting-mock-payment-gateway', (string) config('app.key'));
    }

    /**
     * Keep only non-sensitive fields supported by the mock callback protocol.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callbackAuditData(array $payload): array
    {
        return array_filter(
            Arr::only($payload, ['reference', 'status', 'amount', 'currency']),
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
