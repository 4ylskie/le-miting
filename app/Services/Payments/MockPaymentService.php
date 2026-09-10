<?php

namespace App\Services\Payments;

use App\Models\Payment;

class MockPaymentService implements PaymentProcessor
{
    public function prepare(Payment $payment): array
    {
        return ['url' => route('payment.gateway', $payment)];
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

            return $applier->applyFailedPayment($payload);
        }

        if (($payload['status'] ?? null) === 'paid') {
            return app(PaymentApplier::class)->applyVerifiedPayment($payload);
        }

        return app(PaymentApplier::class)->applyFailedPayment($payload);
    }

    private function signatureIsValid(array $payload): bool
    {
        $secret = (string) config('payment.fiuu.secret_key');

        $reference = $payload['reference'] ?? '';
        $amount = $payload['amount'] ?? '';
        $status = $payload['status'] ?? '';
        $currency = $payload['currency'] ?? 'MYR';

        $expected = hash_hmac('sha256', $secret.$reference.$amount.$status.$currency, $secret);

        return hash_equals($expected, (string) ($payload['signature'] ?? ''));
    }
}
