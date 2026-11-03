<div class="lm-stack">
    <p class="lm-meta lm-muted">{{ config('payment.driver') === 'fiuu' ? 'Fiuu' : 'Stripe Checkout (MYR)' }} handles your online payment.</p>
    @if (config('payment.driver') === 'stripe' && (blank(config('services.stripe.secret')) || str_starts_with((string) config('services.stripe.secret'), 'sk_test_')))
        <p class="lm-meta lm-muted">Test mode: use card <strong>4242 4242 4242 4242</strong>, any future expiry date, and any 3-digit CVC. No real payment is taken.</p>
    @endif
</div>
