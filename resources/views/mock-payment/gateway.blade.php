<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Le Miting Payment Gateway</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="lm-site min-h-screen bg-ivory antialiased">
    <main class="mx-auto max-w-2xl px-(--lm-gutter) py-(--lm-space-xl)">
        <section aria-labelledby="gateway-title" class="lm-stack border-t-4 border-espresso pt-(--lm-space-lg)">
            <header class="lm-stack">
                <p class="lm-eyebrow">Payment Gateway</p>
                <h1 id="gateway-title" class="lm-h3">Online Payment</h1>
            </header>
            <dl class="lm-meta grid gap-(--lm-space-md)">
                <div class="flex justify-between gap-(--lm-space-md)">
                    <dt class="lm-muted">Merchant</dt>
                    <dd>Le Miting</dd>
                </div>
                <div class="flex flex-col gap-(--lm-space-xs) sm:flex-row sm:justify-between sm:gap-(--lm-space-md)">
                    <dt class="lm-muted shrink-0">Transaction reference</dt>
                    <dd class="min-w-0 wrap-anywhere sm:text-right">{{ $payment->provider_reference }}</dd>
                </div>
                <div class="lm-divider flex justify-between gap-(--lm-space-md) pt-(--lm-space-md)">
                    <dt class="font-semibold">Amount due</dt>
                    <dd class="lm-h3 tabular-nums">RM {{ number_format((float) $payment->amount, 2) }}</dd>
                </div>
            </dl>
            <form method="POST" action="{{ $processUrl }}" class="lm-stack">
                @csrf
                <fieldset class="lm-divider pt-(--lm-space-md)">
                    <legend class="lm-label pr-(--lm-space-xs)">Choose a payment method</legend>
                    <p id="method-help" class="lm-meta lm-muted mt-(--lm-space-sm)">Select how you would like to pay. Your payment details are handled by the payment provider.</p>
                    <div class="mt-(--lm-space-sm) grid gap-(--lm-space-xs)">
                        @foreach (['online_banking' => 'Online Banking', 'card' => 'Credit / Debit Card', 'ewallet' => 'E-Wallet'] as $value => $label)
                            <label for="method-{{ $value }}" class="lm-choice">
                                <input id="method-{{ $value }}" type="radio" name="payment_method" value="{{ $value }}" @checked(old('payment_method', 'online_banking') === $value) class="lm-radio" aria-describedby="method-help{{ $errors->has('payment_method') ? ' method-error' : '' }}" @if ($errors->has('payment_method')) aria-invalid="true" @endif>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')
                        <p id="method-error" class="lm-error" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>
                @error('outcome')
                    <p class="lm-error" role="alert">{{ $message }}</p>
                @enderror
                <input type="hidden" name="outcome" value="success">
                <button type="submit" class="lm-button w-full">Pay RM {{ number_format((float) $payment->amount, 2) }}</button>
            </form>
            <form method="POST" action="{{ $processUrl }}">
                @csrf
                <input type="hidden" name="payment_method" value="online_banking">
                <input type="hidden" name="outcome" value="abandoned">
                <button type="submit" class="lm-button lm-button--secondary w-full">Cancel and return without paying</button>
            </form>
            <aside class="lm-divider pt-(--lm-space-lg)" aria-label="Payment verification">
                <p class="lm-label">Online Payment Verification</p>
                <p class="lm-meta lm-muted mt-(--lm-space-xs)">If your payment is declined, you can return to checkout and try again.</p>
                <form method="POST" action="{{ $processUrl }}" class="mt-(--lm-space-xs)">
                    @csrf
                    <input type="hidden" name="payment_method" value="online_banking">
                    <input type="hidden" name="outcome" value="failure">
                    <button type="submit" class="lm-button lm-button--quiet">Return to checkout</button>
                </form>
            </aside>
        </section>
    </main>
</body>
</html>
