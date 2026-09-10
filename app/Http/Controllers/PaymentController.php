<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentProcessor $payments) {}

    public function create(Request $request, Payment $payment)
    {
        abort_unless($payment->status === 'pending', 404);

        $url = $this->payments->prepare($payment)['url'];

        return redirect()->to($url);
    }

    public function gateway(Payment $payment)
    {
        $secret = (string) config('payment.fiuu.secret_key');
        $amount = number_format((float) $payment->amount, 2, '.', '');

        $signature = hash_hmac('sha256', $secret.$payment->provider_reference.$amount.'paid'.'MYR', $secret);
        $failSignature = hash_hmac('sha256', $secret.$payment->provider_reference.$amount.'failed'.'MYR', $secret);

        return view('customer.payment.gateway', [
            'payment' => $payment->load('reservation.table', 'order'),
            'amount' => $amount,
            'paySignature' => $signature,
            'failSignature' => $failSignature,
            'holdMinutes' => config('payment.hold_minutes'),
        ]);
    }

    public function return(Request $request)
    {
        $reference = $request->input('reference') ?? $request->input('bill_code');

        $payment = $reference ? Payment::where('provider_reference', $reference)->first() : null;

        if (! $payment) {
            return view('errors.invalid-link');
        }

        if ($payment->status === 'paid') {
            return redirect()->to($this->confirmationUrl($payment));
        }

        if ($payment->status === 'failed') {
            return redirect()->route('payment.failed');
        }

        return view('customer.payment.return', [
            'payment' => $payment,
        ]);
    }

    public function callback(Request $request)
    {
        try {
            $payment = $this->payments->verify($request->all());
        } catch (\Throwable $e) {
            report($e);

            if (config('payment.driver') === 'mock' && $request->input('status') === 'failed') {
                return redirect()->route('payment.failed');
            }

            return response()->json(['status' => 'FAILED'], 422);
        }

        if (config('payment.driver') === 'mock') {
            if ($payment->status === 'paid') {
                return redirect()->to($this->confirmationUrl($payment));
            }

            return redirect()->route('payment.failed');
        }

        return response()->json(['status' => 'OK']);
    }

    public function failed()
    {
        return view('customer.payment.failed');
    }

    private function confirmationUrl(Payment $payment): string
    {
        if ($payment->reservation) {
            return route('reservation.confirmation', $payment->reservation);
        }

        return route('orders.confirmation', $payment->order);
    }
}
