<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\CartService;
use App\Services\PaymentRetryService;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentProcessor $payments,
        private PaymentRetryService $retries,
        private CartService $carts,
    ) {}

    public function create(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 404);

        $url = $this->payments->prepare($payment)['url'];

        return redirect()->to($url);
    }

    public function return(Request $request): View|RedirectResponse
    {
        $reference = $request->input('reference') ?? $request->input('bill_code');

        $payment = $reference ? Payment::where('provider_reference', $reference)->first() : null;

        if (! $payment) {
            return view('errors.invalid-link');
        }

        if ($payment->status === 'paid') {
            if ($payment->reservation?->status === 'pending') {
                return view('customer.payment.return', ['payment' => $payment, 'reservationUnconfirmed' => true]);
            }

            $this->clearCartFor($payment);

            return redirect()->to($this->confirmationUrl($payment));
        }

        if ($payment->status === 'failed') {
            return redirect()->route('payment.failed', $payment);
        }

        return view('customer.payment.return', [
            'payment' => $payment,
        ]);
    }

    public function callback(Request $request): JsonResponse|RedirectResponse
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
                if ($payment->reservation?->status === 'pending') {
                    return redirect()->route('payment.return', ['reference' => $payment->provider_reference]);
                }

                $this->clearCartFor($payment);

                return redirect()->to($this->confirmationUrl($payment));
            }

            return redirect()->route('payment.failed', $payment);
        }

        return response()->json(['status' => 'OK']);
    }

    public function failed(?Payment $payment = null): View
    {
        return view('customer.payment.failed', [
            'payment' => $payment,
            'canRetry' => $payment !== null && $this->retries->canRetry($payment),
        ]);
    }

    public function retry(Payment $payment): RedirectResponse
    {
        try {
            $payment = $this->retries->retry($payment);
        } catch (ValidationException $exception) {
            return redirect()->route('payment.failed', $payment)->withErrors($exception->errors());
        }

        return redirect()->route('payment.create', $payment);
    }

    private function confirmationUrl(Payment $payment): string
    {
        if ($payment->reservation) {
            return URL::temporarySignedRoute(
                'reservation.confirmation',
                now()->addHours(24),
                ['reservation' => $payment->reservation],
            );
        }

        return URL::temporarySignedRoute(
            'orders.confirmation',
            now()->addHours(24),
            ['order' => $payment->order],
        );
    }

    private function clearCartFor(Payment $payment): void
    {
        if ($payment->order_id !== null) {
            $this->carts->clearOrder();

            return;
        }

        if ($payment->reservation_id !== null) {
            $this->carts->clearReservation();
        }
    }
}
