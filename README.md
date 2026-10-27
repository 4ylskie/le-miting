<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Le Miting operational notes

### DEMO-01 Stage 1: isolated runtime safeguards

Application safeguards are implemented; the demo runtime, schema, account, migrations and administrator are **not provisioned**. In demo mode, only `demo:preflight` is currently permitted. Forward migrations, resets, seeders, workers and administrator provisioning remain blocked until separately approved implementation stages. No force flag bypass exists.

The fixed future runtime is `C:/Users/USER/Herd/le-miting-demo`, separate from this development checkout, with origin `http://127.0.0.1:8011`. These are allowlisted infrastructure identities, not directories or a server created by Stage 1. Changing them requires a reviewed guard change.

After explicit runtime/provisioning approval:

1. Capture a reviewed source snapshot including the approved uncommitted changes; do not assume a Git checkout contains them. Exclude all environment files, credentials, SQLite files/backups, development uploads/storage, logs, sessions, configuration/route caches, `public/hot`, and `public/storage`. Do not invoke Composer setup scripts. Use matching locked dependencies and reviewed built frontend assets without upgrades.
2. Create independent writable `storage/app/private`, `storage/app/public`, `storage/framework/views`, `storage/framework/sessions`, `storage/framework/cache`, `storage/fonts`, `storage/logs` and `bootstrap/cache` directories. Do not copy development cache files. Create the demo runtime's own `public/storage` link to its own `storage/app/public`; shared/junction-resolved writable locations are rejected. Copy approved restaurant assets only in a later authorized content stage, never share writable assets.
3. Create a private `.env.demo` locally, with **no `.env` fallback file**. Generate a new random 32-byte application key locally; never copy the development key. Record SHA-256 fingerprints of the decoded source and demo keys in private `.demo-key-fingerprints.json` using keys `source_key_sha256` and `demo_key_sha256`. Do not print either key or put credentials in command arguments/history. The guard verifies the recorded demo key and rejects equality with the source fingerprint; trusted local setup must establish that source fingerprint correctly. This file contains fingerprints, not keys. Both files are Git-ignored.
4. Explicitly configure `APP_ENV=demo`, `APP_DEBUG=false`, `APP_URL=http://127.0.0.1:8011`, the new `APP_KEY`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=le_miting_demo`, `DB_USERNAME=le_miting_demo` and the separately provisioned private `DB_PASSWORD`. The guard requires Oracle MySQL Community Server 26.7.0 at the approved data directory, UTC sessions and exactly this account at `127.0.0.1`. No alternate connection, URL, read/write host, socket or PDO option is allowed. Schema/account creation still requires separate authorization and a collision check by an authorized administrator.
5. Explicitly configure `SESSION_DRIVER=database`, `SESSION_CONNECTION=mysql`, `SESSION_COOKIE=le-miting-demo-session`, `SESSION_DOMAIN=null`, `CACHE_STORE=database`, `CACHE_PREFIX=le-miting-demo-cache-`, `DB_CACHE_CONNECTION=mysql`, `DB_CACHE_LOCK_CONNECTION=mysql`, `DB_CACHE_LOCK_TABLE=cache_locks`, `MAIL_MAILER=array`, `QUEUE_CONNECTION=sync`, `PAYMENT_DRIVER=mock`, `AI_DRIVER=mock`, `LOG_CHANNEL=single`, and `FILESYSTEM_DISK=local`. Retain UTC application time, default demo-local storage paths and the `sessions`, `cache` and `cache_locks` table names. No previous encryption keys. Discard mail has no delivery receipt or persistent mailbox.
6. Remove FIUU, OpenAI, SMTP and other external-mail credentials from the demo file and launcher environment. Do not copy development environment values into the demo process. The runtime narrows mail to array transport, cache to its database store, filesystems to local/public disks, logging to its local file, and PDF temporary files to its own storage. It disables remote PDF resources. No queue listener or Composer `dev` script is permitted.
7. Run `php artisan demo:preflight --env=demo --no-interaction` from the future runtime. Missing files/schema/account or mismatched identities must fail without fallback. Stage 1 accepts only schema-scoped SELECT, INSERT, UPDATE, DELETE privileges plus global USAGE; migration privileges are intentionally rejected until a separately approved migration stage. Actual account probes must deny both development and test schemas. Reverse account isolation also requires later live verification.
8. Once web launch is separately approved, use a direct loopback PHP server with the working directory set to the demo runtime's `public` directory: `& 'C:/Users/USER/.config/herd/bin/php84/php.exe' -S 127.0.0.1:8011 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` (PowerShell). Laravel's bundled router resolves `index.php` relative to that working directory. Do not use `artisan serve` or a proxy/tunnel. Web requests must have the exact loopback peer, host and port. Confirm server binding locally; request checks are not a replacement for operating-system listener verification. Use a separate browser origin and verify no development login/session is shared.

The early guard selects `.env.demo` before Laravel's environment fallback and rejects cached configuration. Connections are verified before their PDO is exposed, on reconnect and before application SQL. Only the approved MySQL configuration exists; development and test guards retain their existing code. The underlying demo SQL account cannot access protected schemas, but application guards are not a sandbox against an operator editing code or using credentials directly.

Stage 1 tests cover policy rejection and connection/reconnect control flow with synthetic PDO responses, plus the existing guarded MySQL regression suite. They do **not** prove real demo account grants, reverse account isolation, the future storage link, browser-cookie isolation, or an authenticated demo session. Those require live verification after provisioning. The key fingerprint provenance and reviewed snapshot also require operator verification. No demo credentials or administrator are generated by this stage; later provisioning must retain interactive hidden prompts, empty-users refusal, an advisory lock and transactional identity rechecks.

### Reservation cancellation email follow-up

Cancellation is committed before Le Miting attempts to send `ReservationCancellationMail` synchronously. A mail failure does not reverse the cancellation. The application has no automatic retry, delivery-status tracking, or record of successful delivery.

1. Check the configured application log for `Reservation cancellation email send failed.` The warning includes `reservation_id`, `reservation_number`, and `exception_class`, but not the recipient address or exception message. With the example `LOG_CHANNEL=stack` and `LOG_STACK=single`, the log is `storage/logs/laravel.log`; other deployments may use a different configured destination. This warning identifies a failed send attempt, not a failed cancellation.
2. Sign in as an administrator and search the reservation number in `/admin/reservations`. Open the record and confirm its status is `cancelled`, and that its date, table, and guest details match the case. Do not repeat the cancellation or change database records to force another email. If the status is not `cancelled`, investigate the reservation separately before contacting the guest.
3. If follow-up is needed, use the restaurant's existing, verified phone or public email channel and the contact details on the reservation. Confirm the recipient before disclosing reservation details. The Contact Us form is for inbound enquiries, not outbound notifications. In the academic demo, use test contacts only; the example contact details are not a real restaurant channel. State the reservation number and confirmed cancellation status without claiming that an email was received.
4. For a table-and-food reservation, do not infer payment or refund eligibility from the presence of food. Check the actual payment/refund state in the existing authorized records or payment provider before discussing money. Cancellation does not itself issue a refund; any refund decision and processing remain administrator-managed. Do not promise a full, partial, or completed refund without verified authorization and evidence.

A successful `Mail::send()` call means the mail transport accepted the send attempt; it does not prove inbox delivery or customer receipt. The application logs a warning only when the send throws, not every attempt. Absence of a warning is therefore not proof of delivery. A process crash between the database commit and the send can lose the notification without a failure log. If a guest reports missing mail, verify the reservation state through the admin record and use the same manual contact procedure even when no warning exists.

### Full regression test memory

The PHP CLI's default 128 MB limit can be exhausted while Dompdf renders receipts during the full suite. After verifying the guarded connection targets only the isolated `le_miting_test` MySQL schema, run the complete regression suite with a process-local 512 MB limit and strict risky-test detection:

```bash
php -d memory_limit=512M vendor/bin/pest --compact --fail-on-risky --display-all-issues
```

This does not change PHP or application configuration. The last verified run (ADM-16) passed 1,019 tests and 6,288 assertions with no risky tests reported and exit code 0.

### ADM-14 admin visual closeout

Stage 1 corrected primary-action contrast, added a described confirmation dialog for menu-item removal, associated validation errors with admin form fields, distinguished the four report download actions, and corrected page-heading and list-table semantics. Focused admin and accessibility tests cover these changes. Stage 2 used an authorized admin session in Playwright MCP to render 13 admin routes at 1440×900, 768×1024, and 375×812. The mobile navigation and removal dialog were checked for focus and Escape behavior. A reproduced page-level horizontal scrollbar on the mobile menu-item, category, and table lists was removed; their internal table scrolling and row actions remain usable. No browser-console errors were reported.

Order and reservation detail pages were **not** visually inspected because the development database contains no corresponding records; do not treat them as browser-verified. Recheck them when representative, safely provisioned test data is available. Carry smaller row-action touch targets and any accessibility or validation concerns discovered in later testing forward to ADM-15. No development records were created for this closeout.

### ADM-15 accessibility and validation closeout preparation

**Status: ADM-15 approved implementation stages complete; remaining findings documented and deferred.** The following fixes remain present:

- A15-01: Shared admin controls, row actions, pagination links, and associated checkbox labels have enlarged interactive targets; inspected browser targets met the minimum 44×44px requirement.
- A15-02: Reservation status validation errors are visible, including when no transition form is available.
- A15-03: Order/reservation detail-form errors are associated with their fields through `aria-invalid` and `aria-describedby`; valid old selections and form-independent error summaries are preserved.
- A15-04: Repeated row actions have record-specific accessible names, without adding guest names to those action labels.
- A15-06: Menu category, order status, and reservation status filters require explicit **Apply filters** submission rather than navigating on select changes.
- A15-07: Menu create/update requests accept zero, one, or two decimal places and reject extra precision before saving, retaining the existing 0–99999 limits. This matches the existing two-decimal storage and display; no rounding or payment rules changed.
- A15-08: Unsupported order-status filters consistently resolve to all statuses for the query, dropdown, empty state, and pagination; valid filters and other query parameters are preserved.

Automated verification used the guarded MySQL test schema: Stage 1 passed **967 tests / 5,917 assertions**; Stage 2 passed **1,013 tests / 6,195 assertions**. Both strict full-suite runs passed with zero risky tests. Stage 2 focused/adjacent coverage passed **112 tests / 744 assertions**. `tests/Feature/AdminAccessibilityTest.php`, `AdminFilterTest.php`, and `AdminMenuPriceTest.php` cover rendered validation markup, record-specific action names, filter/query/pagination behavior, and create/update price boundaries. These are automated checks, not substitutes for visual or assistive-technology verification.

Authenticated Playwright MCP verification at **1440×900, 768×1024, and 375×812** checked the menu, order, and reservation filter pages. ArrowDown did not navigate; Tab reached the Apply button with visible focus and Enter submitted. Selected filters persisted, invalid order statuses displayed all statuses, inspected visible controls met 44×44px, and no page-level horizontal overflow occurred. Internal menu-table scrolling remained functional at tablet/mobile sizes. Two HTTP 419 console entries occurred during earlier login attempts; successfully verified filter pages reported no errors. Development record counts and content fingerprints remained unchanged; no representative development orders or reservations were created.

The following ADM-15 items were deferred at this stage:

- **A15-05 (resolved by ADM-FIX-02):** Authenticated access to `/admin/login` now redirects to `/admin`; guest login, authorization, and logout remain covered by focused tests.
- **A15-09:** Direct PDF downloads do not provide reliable success/failure confirmation. Current request feedback is intentionally limited and does not claim delivery.
- **Visual coverage (subsequently completed in DEMO-02 Stage 4):** Order and reservation detail pages were not browser-verified in development because it had no representative records; the populated isolated demo later provided that coverage.

This is a scoped implementation/verification record, not a comprehensive WCAG conformance audit. This documentation-only preparation does not implement the deferred findings or begin ADM-16.

### ADM-16 category deletion guidance closeout

**Status: ADM-16 implementation and verification complete; browser coverage limitations documented.** The category list and edit page now use the same counts for not archived, archived, and total assigned menu items. Unavailable items remain in the not archived count; soft-deleted items remain in the archived and total counts. The existing `withTrashed()` guard still blocks deletion while any item references the category. The edit page explains the restriction, offers Delete only when the total is zero, and displays a blocked-deletion validation error as an alert. The ineffective “reassign or deactivate” message was corrected. The existing deletion confirmation and admin authorization remain intact.

Previously completed verification: **68 focused tests / 680 assertions** and **1,019 strict full MySQL tests / 6,288 assertions**, with zero risky tests reported. PHP syntax, scoped Pint, the frontend build, and `git diff --check` passed. Development record counts and content fingerprints were unchanged.

An authorized Playwright session inspected the category list, Appetizers edit page, and menu-item list at **1440×900, 768×1024, and 375×812**. All six categories displayed consistent counts; Appetizers showed **5 not archived + 0 archived = 5 total assigned**. Its edit page explained the deletion block and offered no Delete action. Tab and Enter navigation worked with visible focus; inspected controls met 44×44px, page-level horizontal overflow was absent, internal category-table scrolling worked, and the menu-item list remained usable. No new console errors arose during these checks; two HTTP 419 entries remained from earlier login attempts.

Development has no archived items or empty categories. Archived-only deletion behavior, empty-category confirmation, and blocked-deletion alert rendering were verified by guarded automated tests, **not** in the development browser. [PRD.md §31](PRD.md#31-admin-category-management) now states the approved policy: deletion remains blocked while any menu item references the category, including soft-deleted items.

### ADM final closeout

**ADM-FIX-01, 01B, and 02: resolved.** The two approved landing photographs were restored to the isolated demo runtime. Order and reservation receipts omit unverified restaurant email addresses; mock-payment receipts identify simulated payment rather than implying live FIUU processing. Authenticated visits to `/admin/login` redirect to `/admin`, and customer menu category links meet the 44×44px touch-target minimum. The affected focused tests and desktop/mobile demo browser checks passed; no transaction or payment records were changed.

**Readiness:** The isolated, mock-integrated application is ready for a supervised fictional-data demonstration. This is not a production-readiness assessment: live payment, email delivery, deployment security, and operational reliability remain unverified. PDF download feedback confirms a request, not completed delivery. DEMO-02 transaction records are reconciled, but post-commit notification completion remains unknown; no callbacks or notifications were replayed. Demo payments are simulated and must not be presented as real FIUU charges.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
