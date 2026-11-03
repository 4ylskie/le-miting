@extends('layouts.master')

@section('title', 'Privacy Policy — Le Miting')

@section('content')
    <article class="lm-container lm-section">
        <div class="lm-stack">
            <header class="lm-stack max-w-4xl">
                <p class="lm-eyebrow">Le Miting · Customer policies</p>
                <h1 class="lm-h1">Privacy Policy</h1>
                <p class="lm-meta lm-muted">Effective Date: October 2026 <span aria-hidden="true">·</span> Compliance: Malaysian Personal Data Protection Act 2010 (PDPA — Act 709) &amp; 2024 Amendments</p>
                <p class="lm-body lm-muted">This notice explains what information the website handles, why it is used, and how to contact us about your privacy.</p>
            </header>

            <nav aria-label="Policy sections" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)">
                <h2 class="lm-h2">At a glance</h2>
                <ol class="grid gap-(--lm-space-sm) sm:grid-cols-2 lg:grid-cols-3">
                    <li><a class="underline underline-offset-4" href="#privacy-2-1">2.1 Introduction &amp; notice</a></li>
                    <li><a class="underline underline-offset-4" href="#privacy-2-2">2.2 Data collected</a></li>
                    <li><a class="underline underline-offset-4" href="#privacy-2-3">2.3 Purposes</a></li>
                    <li><a class="underline underline-offset-4" href="#privacy-2-4">2.4 Disclosures</a></li>
                    <li><a class="underline underline-offset-4" href="#privacy-2-5">2.5 Security &amp; retention</a></li>
                    <li><a class="underline underline-offset-4" href="#privacy-2-6">2.6 Your rights</a></li>
                    <li><a class="underline underline-offset-4" href="#privacy-2-7">2.7 Notis Bahasa Malaysia</a></li>
                </ol>
            </nav>

            <div class="lm-stack max-w-4xl">
                <section id="privacy-2-1" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-1">
                    <h2 id="privacy-heading-2-1" class="lm-h2">2.1 Introduction &amp; PDPA Notice</h2>
                    <p class="lm-body">This notice is provided with reference to the Malaysian Personal Data Protection Act 2010 (PDPA — Act 709) and its 2024 amendments. Personal data is information relating directly or indirectly to an identifiable individual. Providing requested information is voluntary, but required fields are needed to submit the relevant order, reservation, or enquiry.</p>
                    <p class="lm-body">Le Miting Dining is responsible for the personal data described in this notice.</p>
                </section>

                <section id="privacy-2-2" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-2">
                    <h2 id="privacy-heading-2-2" class="lm-h2">2.2 Categories of Personal Data Collected</h2>
                    <dl class="lm-stack lm-body">
                        <div class="lm-divider pt-(--lm-space-md)"><dt class="lm-label">1. Identity &amp; Contact Data</dt><dd>Name, email address, and phone number submitted through <a class="underline underline-offset-4" href="{{ route('reservation.create') }}">/reserve</a>, <a class="underline underline-offset-4" href="{{ route('checkout.create') }}">/checkout</a>, and <a class="underline underline-offset-4" href="{{ route('contact.create') }}">/contact</a>.</dd></div>
                        <div class="lm-divider pt-(--lm-space-md)"><dt class="lm-label">2. Reservation &amp; Dining Data</dt><dd>Party size, date and time, table selection, special notes, and reservation check-in status.</dd></div>
                        <div class="lm-divider pt-(--lm-space-md)"><dt class="lm-label">3. Transaction &amp; Order Data</dt><dd>Items ordered, totals, payment status, payment-gateway references, and digital receipts. Raw payment credentials are not requested or stored by the site.</dd></div>
                        <div class="lm-divider pt-(--lm-space-md)"><dt class="lm-label">4. Rasa AI Preference Input</dt><dd>Food preferences and allergen exclusions submitted at <a class="underline underline-offset-4" href="{{ route('ai.recommendations') }}">/ai/recommendations</a> are processed to produce menu suggestions. We do not create permanent medical profiles.</dd></div>
                        <div class="lm-divider pt-(--lm-space-md)"><dt class="lm-label">5. Technical &amp; Session Data</dt><dd>IP address, user-agent, CSRF security tokens, cart session data, and rate-limiting or security logs may be processed to operate and protect the site.</dd></div>
                    </dl>
                </section>

                <section id="privacy-2-3" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-3">
                    <h2 id="privacy-heading-2-3" class="lm-h2">2.3 Purpose of Processing Personal Data</h2>
                    <p class="lm-body">Information is used only for service and operational purposes, including fulfilling orders and reservations; verifying payments and preparing receipts; sending urgent order or booking updates; replying to Contact Us enquiries; issuing a one-time post-visit review invitation; and maintaining security, preventing misuse, and meeting applicable statutory obligations.</p>
                </section>

                <section id="privacy-2-4" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-4">
                    <h2 id="privacy-heading-2-4" class="lm-h2">2.4 Disclosure to Third Parties</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li>Personal data is not sold or rented.</li>
                        <li>Information may be shared with {{ config('payment.driver') === 'fiuu' ? 'Fiuu' : 'Stripe' }} as payment processor and transactional email infrastructure as needed to complete the requested service. Card details are entered only on the hosted payment page and are not stored by Le Miting.</li>
                        <li>When an AI provider is configured, only anonymized dish identifiers and necessary preference input are sent for recommendations; customer contact details are not included.</li>
                        <li>Information may be disclosed to regulatory or law-enforcement authorities when required by law.</li>
                    </ul>
                </section>

                <section id="privacy-2-5" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-5">
                    <h2 id="privacy-heading-2-5" class="lm-h2">2.5 Data Security &amp; Retention</h2>
                    <p class="lm-body">The application uses security controls including Content Security Policy (CSP), CSRF protection, hashed passwords, and role-based access control (RBAC). No online system can guarantee absolute security; do not submit card numbers, CVV codes, or banking credentials through this site.</p>
                    <p class="lm-body">Transient session data expires automatically according to session settings. In a commercial operation, transaction and accounting records may be retained for up to seven years where statutory tax and accounting requirements apply. Other information should be retained only as long as required for its stated purpose or applicable law.</p>
                </section>

                <section id="privacy-2-6" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-6">
                    <h2 id="privacy-heading-2-6" class="lm-h2">2.6 Your Rights Under the PDPA</h2>
                    <ul role="list" class="lm-stack lm-body list-disc pl-6">
                        <li><strong>Access:</strong> request access to personal data held about you.</li>
                        <li><strong>Correction:</strong> request correction of inaccurate or incomplete information.</li>
                        <li><strong>Consent withdrawal:</strong> withdraw consent where processing relies on consent, subject to applicable legal or contractual limits.</li>
                        <li><strong>Data portability or deletion:</strong> request portability where applicable or ask for deletion, subject to identity verification, legal retention duties, and other lawful grounds to retain information.</li>
                    </ul>
                    <p class="lm-body">Submit a request through <a class="underline underline-offset-4" href="{{ route('contact.create') }}">/contact</a>. Include a relevant order or reservation reference if useful, but never include payment credentials.</p>
                </section>

                <section id="privacy-2-7" class="lm-stack rounded-xl border border-line bg-surface p-(--lm-space-lg)" aria-labelledby="privacy-heading-2-7" lang="ms">
                    <h2 id="privacy-heading-2-7" class="lm-h2">2.7 Notis Privasi Ringkas <span class="lm-meta">(Bahasa Malaysia — PDPA)</span></h2>
                    <p class="lm-body">Selaras dengan Akta Perlindungan Data Peribadi 2010 (PDPA), Le Miting mengumpul maklumat peribadi anda (seperti nama, nombor telefon, alamat e-mel, butiran tempahan meja, dan pesanan makanan) semata-mata untuk memproses pesanan, mengesahkan pembayaran, mengurus tempahan meja, dan menghubungi anda berkaitan perkhidmatan kami. Data anda tidak akan dijual kepada mana-mana pihak ketiga. Anda berhak memohon akses atau pembetulan ke atas data peribadi anda pada bila-bila masa melalui halaman Hubungi Kami (/contact).</p>
                </section>
            </div>

            <footer class="lm-divider pt-(--lm-space-lg)">
                <nav aria-label="Other policies and contact" class="lm-actions">
                    <a class="lm-button lm-button--quiet" href="{{ route('policies.terms') }}">Terms &amp; Conditions</a>
                    <a class="lm-button lm-button--quiet" href="{{ route('policies.cancellation') }}">Cancellation &amp; Refund Policy</a>
                    <a class="lm-button lm-button--quiet" href="{{ route('contact.create') }}">Contact</a>
                </nav>
            </footer>
        </div>
    </article>
@endsection
