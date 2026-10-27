<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Arr;

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
        $reference = $this->callbackField($payload, 'bill_code', 'reference');
        $status = $this->callbackField($payload, 'bill_status', 'status');
        $amount = $this->callbackField($payload, 'bill_amount', 'amount');

        if (! is_numeric($amount) || ! is_finite((float) $amount)) {
            throw new \InvalidArgumentException('Invalid payment amount.');
        }

        $payment = Payment::where('provider_reference', $reference)->first();

        if (! $payment) {
            throw new \InvalidArgumentException('Unknown payment reference.');
        }

        if (! $this->callbackSignatureIsValid($reference, $amount, $status, $payload['signature'] ?? null)) {
            throw new \InvalidArgumentException('Invalid payment signature.');
        }

        if (abs((float) $amount - (float) $payment->amount) > 0.01) {
            return app(PaymentApplier::class)->applyFailedPayment([
                'reference' => $reference,
                'amount' => $amount,
                'raw_response' => $this->callbackAuditData($payload),
            ]);
        }

        if ($status === 'paid') {
            return app(PaymentApplier::class)->applyVerifiedPayment([
                'reference' => $reference,
                'amount' => $amount,
                'currency' => 'MYR',
                'raw_response' => $this->callbackAuditData($payload),
            ]);
        }

        return app(PaymentApplier::class)->applyFailedPayment([
            'reference' => $reference,
            'amount' => $amount,
            'raw_response' => $this->callbackAuditData($payload),
        ]);
    }

    private function signature(array $params): string
    {
        return md5(config('payment.fiuu.secret_key').$params['merchant_id'].$params['bill_code']);
    }

    /**
     * Resolve existing adapter aliases without changing the signed text.
     * Both aliases, when supplied, must represent the same exact text.
     *
     * @param  array<string, mixed>  $payload
     */
    private function callbackField(array $payload, string $field, string $alias): string
    {
        $values = [];

        foreach ([$field, $alias] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];

            if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || (string) $value === '') {
                throw new \InvalidArgumentException('Malformed payment callback.');
            }

            $values[] = (string) $value;
        }

        if ($values === [] || (count($values) === 2 && $values[0] !== $values[1])) {
            throw new \InvalidArgumentException('Missing or conflicting payment callback fields.');
        }

        return $values[0];
    }

    private function callbackSignatureIsValid(string $reference, string $amount, string $status, mixed $signature): bool
    {
        $secret = (string) config('payment.fiuu.secret_key');

        $expected = md5($secret.$reference.$amount.$status);

        return is_string($signature) && hash_equals($expected, $signature);
    }

    /**
     * Keep only the callback fields already used by this integration.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function callbackAuditData(array $payload): array
    {
        return array_filter(
            Arr::only($payload, ['bill_code', 'reference', 'bill_status', 'status', 'bill_amount', 'amount', 'currency']),
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
