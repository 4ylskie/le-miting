<?php

namespace App\Services\Payments;

use App\Models\Payment;

class FiuuPaymentService implements PaymentProcessor
{
    public function prepare(Payment $payment): array
    {
        $merchantId = (string) config('payment.fiuu.merchant_id');
        $reference = (string) $payment->provider_reference;
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $billName = $payment->reservation ? 'Le Miting - Reservation Pre-order' : 'Le Miting - Food Order';

        $params = [
            'merchant_id' => $merchantId,
            'bill_code' => $reference,
            'bill_amount' => $amount,
            'bill_desc' => $billName,
            'bill_return' => config('payment.fiuu.return_url'),
            'bill_callback' => config('payment.fiuu.callback_url'),
        ];

        $params['signature'] = $this->signature($params);

        return ['url' => rtrim((string) config('payment.fiuu.base_url'), '/').'/page?'.http_build_query($params)];
    }

    public function verify(array $payload): Payment
    {
        $reference = $payload['bill_code'] ?? $payload['reference'] ?? null;
        $status = $payload['status'] ?? $payload['bill_status'] ?? null;
        $amount = $payload['bill_amount'] ?? $payload['amount'] ?? null;

        if (! $reference) {
            throw new \InvalidArgumentException('Missing payment reference.');
        }

        $payment = Payment::where('provider_reference', $reference)->first();

        if (! $payment) {
            throw new \InvalidArgumentException('Unknown payment reference.');
        }

        if (! $this->callbackSignatureIsValid($payload)) {
            throw new \InvalidArgumentException('Invalid payment signature.');
        }

        if ($amount !== null && abs((float) $amount - (float) $payment->amount) > 0.01) {
            return app(PaymentApplier::class)->applyFailedPayment([
                'reference' => $reference,
                'amount' => $amount,
            ]);
        }

        if ($status === 'paid') {
            return app(PaymentApplier::class)->applyVerifiedPayment([
                'reference' => $reference,
                'amount' => $amount,
                'currency' => 'MYR',
            ]);
        }

        return app(PaymentApplier::class)->applyFailedPayment([
            'reference' => $reference,
            'amount' => $amount,
        ]);
    }

    private function signature(array $params): string
    {
        return md5(config('payment.fiuu.secret_key').$params['merchant_id'].$params['bill_code']);
    }

    private function callbackSignatureIsValid(array $payload): bool
    {
        $secret = (string) config('payment.fiuu.secret_key');
        $reference = $payload['bill_code'] ?? $payload['reference'] ?? '';
        $amount = $payload['bill_amount'] ?? $payload['amount'] ?? '';
        $status = $payload['bill_status'] ?? $payload['status'] ?? '';

        $expected = md5($secret.$reference.$amount.$status);

        return isset($payload['signature']) && hash_equals($expected, (string) $payload['signature']);
    }
}
