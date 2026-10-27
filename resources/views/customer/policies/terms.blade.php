@extends('layouts.master')

@section('title', 'Terms & Conditions — Le Miting')

@section('content')
    <article class="lm-container lm-section">
        <div class="lm-stack">
            <header class="lm-stack max-w-4xl">
                <p class="lm-eyebrow">Le Miting · Customer policies</p>
                <h1 class="lm-h1">Terms &amp; Conditions</h1>
                <p class="lm-meta lm-muted">Effective Date: October 2026 <span aria-hidden="true">·</span> Entity: Le Miting Dining <span aria-hidden="true">·</span> Jurisdiction: Malaysia</p>
                <p class="lm-body lm-muted">These terms apply to your use of the Le Miting website, menu, reservations, orders, and payment services.</p>
            </header>

            <nav aria-label="Policy sections" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)">
                <h2 class="lm-h2">At a glance</h2>
                <ol class="grid gap-(--lm-space-sm) sm:grid-cols-2 lg:grid-cols-3">
                    <li><a class="underline underline-offset-4" href="#terms-1-1">1.1 Acceptance</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-2">1.2 Services</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-3">1.3 Menu &amp; pricing</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-4">1.4 Food safety &amp; Rasa AI</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-5">1.5 Reservations</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-6">1.6 Orders &amp; payment</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-7">1.7 Intellectual property</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-8">1.8 Liability &amp; law</a></li>
                    <li><a class="underline underline-offset-4" href="#terms-1-9">1.9 Cancellations &amp; refunds</a></li>
                </ol>
            </nav>

            <div class="lm-stack max-w-4xl">
                <section id="terms-1-1" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-1">
                    <h2 id="terms-heading-1-1" class="lm-h2">1.1 Acceptance of Terms</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>By browsing or using this website, submitting an online order, making a table reservation, or using the Rasa AI Menu Concierge, you agree to these terms.</li>
                        <li>You must provide accurate information, review your selections before submission, and use the service lawfully.</li>
                    </ul>
                </section>

                <section id="terms-1-2" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-2">
                    <h2 id="terms-heading-1-2" class="lm-h2">1.2 Scope of Services</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>The site presents a curated menu with prices in Malaysian Ringgit (RM/MYR), subject to the availability and information shown at the time of submission.</li>
                        <li><a class="underline underline-offset-4" href="{{ route('ai.recommendations') }}">Rasa AI Menu Concierge</a> at <code>/ai/recommendations</code> provides menu suggestions from the available menu and selected preferences.</li>
                        <li><a class="underline underline-offset-4" href="{{ route('reservation.create') }}">Reservations</a> start at <code>/reserve</code>; the interactive floor plan supports table selection.</li>
                        <li>Online payments are processed through Fiuu or the payment provider shown at checkout. An order is confirmed after payment authorization or verification succeeds.</li>
                    </ul>
                </section>

                <section id="terms-1-3" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-3">
                    <h2 id="terms-heading-1-3" class="lm-h2">1.3 Menu Information, Pricing &amp; Availability</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>Prices are stated in MYR. The amount shown at checkout is the amount submitted for payment, subject to gateway verification.</li>
                        <li>Kitchen availability may change. If an item is unavailable after an order is accepted, the customer is eligible for a full refund for the unavailable item or an agreed replacement.</li>
                        <li>Descriptions and editorial photography are illustrative representations of menu items; presentation and ingredients may vary.</li>
                    </ul>
                </section>

                <section id="terms-1-4" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-4">
                    <h2 id="terms-heading-1-4" class="lm-h2">1.4 Allergen, Dietary &amp; Rasa AI Disclaimer</h2>
                    <p class="lm-alert lm-allergen-caution" data-state="warning" role="note"><strong>Critical Food Safety Notice:</strong> Our kitchen handles peanuts, tree nuts, milk/dairy, eggs, soy, wheat/gluten, fish, crustacean shellfish, molluscs, and sesame. Cross-contact may occur. No dish is guaranteed “allergy-safe” or “allergen-free”.</p>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>REC-02C filtering excludes a dish only when both recipe and cross-contact evidence are current and assessed as <code>reviewed_absent</code> and <code>reviewed_no_known_risk</code>, respectively.</li>
                        <li>Unknown, invalidated, or unmapped allergen evidence fails closed: affected dishes are withheld from filtered recommendations. This is a limited filtering control, not a finding that a dish is safe.</li>
                        <li>Rasa AI suggestions and dietary labels are informational and do not assess medical needs or guarantee suitability.</li>
                        <li>Suggestions and dietary labels are not a guarantee that a dish is suitable for an allergy.</li>
                        <li>You must inform restaurant staff directly about every allergy or dietary requirement before ordering. Do not rely on this website or AI suggestions for food-safety decisions.</li>
                    </ul>
                </section>

                <section id="terms-1-5" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-5">
                    <h2 id="terms-heading-1-5" class="lm-h2">1.5 Table Reservations &amp; Dine-In Conduct</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>Provide accurate guest contact details and party size. Interactive floor-plan selection is subject to the table and time shown as available when the request is submitted.</li>
                        <li>A 15-minute grace period applies after the reservation time. After that, an unannounced non-arrival may be recorded as a No-Show and the table released.</li>
                        <li>Plan for a dining window of approximately 90–120 minutes, depending on the reservation and service conditions.</li>
                    </ul>
                </section>

                <section id="terms-1-6" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-6">
                    <h2 id="terms-heading-1-6" class="lm-h2">1.6 Online Orders &amp; Payment Processing</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>An order is confirmed only after payment authorization or verification succeeds and a confirmation is issued. A pending payment is not confirmation.</li>
                        <li>Payment providers process transactions through their payment flows. Orders are confirmed after payment authorization or verification succeeds.</li>
                        <li>The site does not store raw card numbers, CVV codes, or online banking credentials. Do not send payment credentials through forms or contact messages.</li>
                    </ul>
                </section>

                <section id="terms-1-7" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-7">
                    <h2 id="terms-heading-1-7" class="lm-h2">1.7 Intellectual Property &amp; Acceptable Use</h2>
                    <p class="lm-body">Website text, design, software, and original materials are protected by applicable intellectual-property laws. Menu names and third-party materials remain with their respective rights holders. You may use the site for personal browsing, ordering, and reservation requests; you may not disrupt the service, probe it without authorization, submit unlawful content, or copy and commercially reuse protected material without permission.</p>
                </section>

                <section id="terms-1-8" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-8">
                    <h2 id="terms-heading-1-8" class="lm-h2">1.8 Limitation of Liability &amp; Governing Law</h2>
                    <p class="lm-body">Nothing in these terms excludes rights or remedies that cannot lawfully be excluded under the Malaysian Consumer Protection Act 1999 (Act 599) or other applicable law. These terms are governed by the laws of Malaysia, and Malaysian courts have jurisdiction, subject to mandatory consumer protections.</p>
                </section>

                <section id="terms-1-9" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="terms-heading-1-9">
                    <h2 id="terms-heading-1-9" class="lm-h2">1.9 Refund, Cancellation &amp; No-Show Summary</h2>
                    <p class="lm-body">The detailed <a class="underline underline-offset-4" href="{{ route('policies.cancellation') }}">Refund, Cancellation &amp; No-Show Policy</a> applies to order cancellation, reservation changes, no-shows, order issues, and processing times.</p>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>Eligible online food orders cancelled before kitchen preparation begins receive a full refund; orders in preparation, ready, or completed are non-refundable for change of mind.</li>
                        <li>Standard table-only reservations may be cancelled or rescheduled up to two hours before the reservation. Pre-orders cancelled more than three hours beforehand qualify for a full refund; later requests may be non-refundable.</li>
                        <li>Restaurant-initiated cancellations, unavailable items, verified duplicate charges, and qualifying incorrect, missing, or defective dishes are eligible for the remedy described in the detailed policy.</li>
                    </ul>
                </section>
            </div>

            <footer class="lm-divider pt-(--lm-space-lg)">
                <nav aria-label="Other policies and contact" class="lm-actions">
                    <a class="lm-button lm-button--quiet" href="{{ route('policies.privacy') }}">Privacy Policy</a>
                    <a class="lm-button lm-button--quiet" href="{{ route('policies.cancellation') }}">Cancellation &amp; Refund Policy</a>
                    <a class="lm-button lm-button--quiet" href="{{ route('contact.create') }}">Contact</a>
                </nav>
            </footer>
        </div>
    </article>
@endsection
