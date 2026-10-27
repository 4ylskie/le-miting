<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteMockPaymentRequest;
use App\Models\Payment;
use App\Services\Payments\MockPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class MockPaymentGatewayController extends Controller
{
    public function __construct(private MockPaymentService $payments) {}

    public function show(Payment $payment): View
    {
        $this->ensureEligible($payment);

        return view('mock-payment.gateway', [
            'payment' => $payment,
            'processUrl' => URL::temporarySignedRoute(
                'mock-payment.process',
                now()->addMinutes((int) config('payment.hold_minutes')),
                ['payment' => $payment],
            ),
        ]);
    }

    public function process(CompleteMockPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->ensureEligible($payment);

        if ($request->validated('outcome') !== 'abandoned') {
            $this->payments->processDemoOutcome($payment, $request->validated('outcome'));
        }

        return redirect()->route('payment.return', ['reference' => $payment->provider_reference]);
    }

    private function ensureEligible(Payment $payment): void
    {
        abort_unless(config('payment.driver') === 'mock' && $payment->status === 'pending', 404);
    }
}
