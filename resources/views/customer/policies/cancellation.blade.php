@extends('layouts.master')

@section('title', 'Cancellation & Refund Policy — Le Miting')

@section('content')
    <article class="lm-container lm-section">
        <div class="lm-stack">
            <header class="lm-stack max-w-4xl">
                <p class="lm-eyebrow">Le Miting · Customer policies</p>
                <h1 class="lm-h1">Cancellation &amp; Refund Policy</h1>
                <p class="lm-meta lm-muted">Effective Date: October 2026 <span aria-hidden="true">·</span> Entity: Le Miting Dining <span aria-hidden="true">·</span> Jurisdiction: Malaysia</p>
                <p class="lm-body lm-muted">Review the applicable timing and remedy before placing an order or submitting a reservation with pre-ordered food.</p>
            </header>

            <nav aria-label="Policy sections" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)">
                <h2 class="lm-h2">At a glance</h2>
                <ol class="grid gap-(--lm-space-sm) sm:grid-cols-2">
                    <li><a class="underline underline-offset-4" href="#refund-3-1">3.1 Food order cancellation</a></li>
                    <li><a class="underline underline-offset-4" href="#refund-3-2">3.2 Reservations &amp; no-shows</a></li>
                    <li><a class="underline underline-offset-4" href="#refund-3-3">3.3 Order issue refunds</a></li>
                    <li><a class="underline underline-offset-4" href="#refund-3-4">3.4 Processing &amp; timelines</a></li>
                </ol>
            </nav>

            <div class="lm-stack max-w-4xl">
                <section id="refund-3-1" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="refund-heading-3-1">
                    <h2 id="refund-heading-3-1" class="lm-h2">3.1 Online Food Orders — Cancellations &amp; Modifications</h2>
                    <ol role="list" class="lm-stack lm-body list-decimal pl-6">
                        <li><strong>Before kitchen preparation begins (Pending):</strong> an order in Pending status is eligible for cancellation and a 100% full refund. Request cancellation promptly through <a class="underline underline-offset-4" href="{{ route('contact.create') }}">Contact</a>, providing the order reference.</li>
                        <li><strong>After kitchen preparation begins (Preparing / Ready / Completed):</strong> the order is non-cancellable and non-refundable for change of mind. This does not limit remedies for an incorrect, missing, defective, or unavailable item described in section 3.3.</li>
                        <li>Modification requests are subject to kitchen status and availability. A request is not accepted until confirmed.</li>
                    </ol>
                </section>

                <section id="refund-3-2" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="refund-heading-3-2">
                    <h2 id="refund-heading-3-2" class="lm-h2">3.2 Table Reservations &amp; Pre-Order Cancellations</h2>
                    <ol role="list" class="lm-stack lm-body list-decimal pl-6">
                        <li><strong>Standard table reservations (no pre-order):</strong> cancellation or rescheduling is free up to two hours before the scheduled reservation time. Use the cancellation link in the confirmation email where available, or contact us with the reservation number. A table-only booking has no online payment to refund.</li>
                        <li><strong>Reservations with pre-ordered dishes:</strong> cancellation more than three hours before the reservation qualifies for a 100% refund of the pre-order payment. Less than three hours before the reservation, or after preparation starts, the pre-order is non-refundable. You may contact us to request conversion to takeaway collection; this is subject to preparation status and confirmation.</li>
                        <li><strong>No-Show policy:</strong> a 15-minute grace period applies. If you do not arrive and have not notified the restaurant within that period, the reservation may be marked No-Show and the table released. Prepared pre-ordered food is non-refundable.</li>
                        <li>Cancellation links are private and may expire. If a link is unavailable or expired, contact us with your reservation reference.</li>
                    </ol>
                </section>

                <section id="refund-3-3" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="refund-heading-3-3">
                    <h2 id="refund-heading-3-3" class="lm-h2">3.3 Refund Eligibility for Order Issues</h2>
                    <p class="lm-body">This policy operates consistently with applicable rights under the Malaysian Consumer Protection Act 1999 (Act 599). Statutory rights and remedies are not limited by these procedures.</p>
                    <ol role="list" class="lm-stack lm-body list-decimal pl-6">
                        <li><strong>Restaurant-initiated cancellation or out-of-stock item:</strong> a 100% refund applies to the cancelled order or unavailable item. Where suitable, you may agree to a replacement instead.</li>
                        <li><strong>Incorrect, missing, or defective dishes:</strong> report the issue immediately or within two hours of receipt, with your Order Reference and a description of the affected dish. You may choose an immediate freshly prepared replacement or a 100% refund for the affected dish.</li>
                        <li><strong>Duplicate or erroneous charges:</strong> a 100% refund is issued after the gateway transaction is verified. Include the order reference and relevant transaction reference; do not send card numbers, CVV, or banking credentials.</li>
                    </ol>
                </section>

                <section id="refund-3-4" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="refund-heading-3-4">
                    <h2 id="refund-heading-3-4" class="lm-h2">3.4 Refund Processing Method &amp; Timelines</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>Approved refunds are credited to the original payment method used for the transaction.</li>
                        @if (config('payment.driver') === 'fiuu')
                            <li><strong>FPX / Online Banking and E-Wallets (via Fiuu):</strong> typically 3 to 5 business days after processing.</li>
                        @else
                            <li>Stripe processes approved refunds to the original payment method. Test-mode transactions do not move real money.</li>
                        @endif
                        <li><strong>Credit / Debit Cards:</strong> typically 5 to 14 business days after processing. The issuing bank may affect when the credit appears.</li>
                    </ul>
                    <p class="lm-body">For help, use <a class="underline underline-offset-4" href="{{ route('contact.create') }}">Contact</a> and include your order or reservation reference. Do not include payment credentials.</p>
                </section>
            </div>

            <footer class="lm-divider pt-(--lm-space-lg)">
                <nav aria-label="Other policies and contact" class="lm-actions">
                    <a class="lm-button lm-button--quiet" href="{{ route('policies.terms') }}">Terms &amp; Conditions</a>
                    <a class="lm-button lm-button--quiet" href="{{ route('policies.privacy') }}">Privacy Policy</a>
                    <a class="lm-button lm-button--quiet" href="{{ route('contact.create') }}">Contact</a>
                </nav>
            </footer>
        </div>
    </article>
@endsection
