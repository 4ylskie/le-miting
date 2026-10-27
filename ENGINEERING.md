This document answers: in what order should everything be built, what does each task depend on, how should it be tested, and what counts as done?

Product requirements → PRD.md Architecture and integrations → TECHNICAL_DESIGN.md Screen and system flow → APP_FLOW.md Visual design and UI behaviour → UI_UX_BRIEF.md Database, models, routes, auth → BACKEND_DESIGN.md

Naming Rules
Concept	Correct name
Payment gateway	FIUU
AI feature	Rasa AI
Admin role	admin / administrator
Customer	guest

1. Engineering Objective

Build and deploy the Le Miting restaurant ordering and reservation system using Laravel, Blade, Tailwind CSS, MySQL, FIUU, OpenAI API (Rasa AI), Laravel Mail, and Laravel DomPDF.

Development prioritises core restaurant workflows first, then integrations, administration, testing, security, and deployment.

2. Development Phases
text
Phase 1  — Project Setup
      ↓
Phase 2  — Database and Models
      ↓
Phase 3  — Customer Frontend: Menu, Ordering, Contact Us
      ↓
Phase 4  — Reservation System
      ↓
Phase 5  — Payment Integration (FIUU + ReceiptService)
      ↓
Phase 6  — Rasa AI and Email
      ↓
Phase 7  — Admin Panel
      ↓
Phase 8  — Reservation Identifiers, External Google Review Invitations, and Reports
      ↓
Phase 9  — Testing
      ↓
Phase 10 — Security Hardening
      ↓
Phase 11 — Deployment

Each phase depends on all prior phases being complete. Do not begin a phase until its dependencies are working.

3. Phase 1 — Project Setup

Goal: working Laravel application connected to MySQL, with all third-party packages installed and credentials configured.

Tasks
Create Laravel project
Configure .env (database, mail, FIUU credentials, OpenAI API key, app key, app URL)
Configure MySQL database connection
Configure Blade templating
Install and configure Tailwind CSS
Install barryvdh/laravel-dompdf
Configure Laravel Mail (SMTP)
Set up Git repository and .gitignore (exclude .env, vendor/, node_modules/)
Establish base Laravel application structure (layouts, route files, config)
Verify .env is not committed to Git
Deliverable

Working Laravel application connected to MySQL. All packages installed. Credentials configured and excluded from version control.

4. Phase 2 — Database and Models

Depends on: Phase 1

Goal: complete database structure with working Eloquent relationships, ready for application logic to build on.

Migrations (in dependency order)
text
users
menu_categories
menu_items          (soft deletes — deleted_at)
tables
orders
order_items
payments
reservations
reservation_foods
Migration tasks
users — name, email, password, role (default admin), remember_token, timestamps
menu_categories — name, slug, sort_order, is_active, timestamps
menu_items — menu_category_id (FK), name, slug, description, price, image_path, spice_level, protein_type, dietary_type, flavor_profile, is_available, is_featured, sort_order, deleted_at, timestamps
tables — table_number, capacity, status (available / maintenance), timestamps
orders — order_number, customer_name, customer_email, customer_phone, order_type (dine-in / takeaway), status, total, notes, timestamps
order_items — order_id (FK), menu_item_id (FK nullable), item_name, unit_price, quantity, timestamps
payments — order_id (FK nullable), reservation_id (FK nullable), provider, provider_reference (unique), status, amount, currency, raw_response, paid_at, timestamps
reservations — table_id (FK), reservation_number, reservation_type, customer_name, customer_email, customer_phone, guest_count, reservation_date, reservation_time, duration_minutes (default 120), status, notes, timestamps
reservation_foods — reservation_id (FK), menu_item_id (FK nullable), item_name, unit_price, quantity, timestamps
Model tasks
Create Eloquent model for each table
Define $fillable on every model (no $guarded = [])
Define soft deletes on MenuItem
Define all relationships (see BACKEND_DESIGN.md §5.2)
Verify Payment mutual-exclusivity (order_id XOR reservation_id)
Seeder tasks
AdminSeeder — provisions the initial admin account (role = admin)
MenuCategorySeeder — seed initial categories
MenuItemSeeder — seed initial menu items with all attribute fields
TableSeeder — seed restaurant tables with capacity
Deliverable

All migrations run cleanly. All Eloquent models defined with correct relationships. Seeders run and produce valid data.

5. Phase 3 — Customer Frontend: Menu, Ordering, and Contact Us

Depends on: Phase 2

Goal: guest can browse the menu, build a cart, reach checkout, and submit a contact enquiry. Rasa AI remains on its dedicated page; no Contact Us recommendation widget is planned.

Landing page
HomeController@index → display landing page
Hero section with Order and Reserve CTAs
Featured menu items section (query MenuItem where is_featured = true)
Restrained editorial landing composition using approved photography and verified restaurant content only; omit unsupported story, testimonials, ratings, and social proof
Verified restaurant contact information and opening hours when configured
Navigation to Menu / Order, Reserve, Contact Us
Menu
MenuController@index → display menu with categories, search, filters
MenuController@show → display single menu item
Category filter (query by menu_category_id)
Search by item name
Attribute filters (spice_level, protein_type, dietary_type, flavor_profile)
Customer menu discovery, featured dishes, item detail, and Rasa AI query only `is_available = true` items. If an item becomes unavailable after it has entered a cart, keep it visible there solely for removal; exclude it from totals and block checkout until removed.
No rating display in menu discovery interfaces — ratings are not stored on menu_items (V1 decision)
Empty state when no items match search or filters
Cart (session-based)
CartController@index → view cart
CartController@store → add item (verify is_available = true)
CartController@update → update quantity
CartController@destroy → remove item
CartController@clear → clear cart
Cart stored in Laravel session — no database table (`cart.order` for normal orders and `cart.reservation` for Table + Food)
Empty cart state with link back to menu
Cart total calculated from live menu_items.price — never from client-submitted values
Checkout (order creation — stops before payment)
CheckoutController@create → display checkout form
CheckoutController@store → StoreOrderRequest validates input
Verify all cart items still is_available = true
Calculate total server-side from database prices
Create Order (status: pending)
Create OrderItem rows — snapshot item_name + unit_price
Keep `cart.order` while payment is pending; clear it only after verified payment confirms the order
Redirect to FIUU (implemented in Phase 5)
Contact Us
ContactController@create → display Contact Us page
Verified restaurant address, phone, email, and operating hours when configured
Contact form (name, email, message — all required)
No embedded recommendation widget
ContactController@store → StoreContactRequest validates input
Dispatch ContactEnquiryMail to restaurant
Success response on send, error response on failure
Preserve form data on failure — guest does not re-type
Deliverable

Guest can browse menu, manage cart, reach checkout, and submit a contact enquiry. Order is created in the database in pending status. FIUU redirect not yet functional.

6. Phase 4 — Reservation System

Depends on: Phase 2 (models, tables), Phase 3 (frontend patterns established)

Goal: guest can find an available table and create both reservation types. Payment redirect for table_and_food wired in Phase 5.

Tasks
ReservationController@create → display reservation type selection (table_only / table_and_food)
ReservationController@availability → check table availability
DB transaction with lockForUpdate()
Query reservations for overlapping window on table_id
Window: reservation_time to reservation_time + 120 minutes
Exclude pending rows older than 15 minutes
Return available tables for the requested slot
ReservationController@store → StoreReservationRequest validates input
Validate guest_count <= tables.capacity
Re-check availability inside DB transaction (race condition protection)
table_only: create Reservation (status: confirmed immediately) → reservation number and email triggered in Phase 8
table_and_food: create Reservation (status: pending) + ReservationFood rows (snapshot item_name + unit_price) → redirect to FIUU (Phase 5)
ReservationController@confirmation → display reservation confirmation page
ReservationController@cancelForm (signed, 24-hour expiry) → display cancellation confirmation form
ReservationController@cancel (signed, 24-hour expiry) → process cancellation
Set status: cancelled
Free table slot
ReservationCancellationMail sent (Phase 6 — wire in once Mailables exist)
Empty and conflict states for availability check
15-minute hold implementation

The availability query filters out pending reservations where created_at < now() - 15 minutes. No cron job required for correctness. An optional artisan cleanup command may set stale rows to cancelled for admin list hygiene — not required for V1.

Deliverable

Guest can create both reservation types. table_only reservations confirm immediately. table_and_food reservations sit as pending awaiting payment (Phase 5).

7. Phase 5 — Payment Integration (FIUU + ReceiptService)

Depends on: Phase 3 (order creation), Phase 4 (reservation creation)

Goal: FIUU payment flows work for both orders and reservations. Receipt generation is implemented here because it is triggered by the verified payment callback.

Development and demonstrations use a signed mock payment gateway when FIUU is not configured. It offers non-sensitive fake methods and controlled success, failure, or abandonment outcomes; server-side mock HMAC verification, authoritative stored amounts, callback handling, return handling, retry, and idempotency use the same application lifecycle. This is not a claim of FIUU protocol compatibility and never processes real funds.

FiuuPaymentService
FiuuPaymentService::prepare(Order|Reservation) — build payment request, generate reference
FiuuPaymentService::verify(array $callbackData) — verify callback signature, match reference, verify amount
Credentials from .env — never hardcoded
Order payment flow
GET /payment/return → PaymentController@return
Display "verifying payment" state only — not proof of payment
POST /payment/callback → PaymentController@callback
Call FiuuPaymentService::verify()
Upsert Payment row (order_id set, reservation_id null) keyed on provider_reference
On verified: Order.status → confirmed, Payment.status → paid
On failed: Payment.status → failed, Order remains pending
Trigger OrderConfirmationMail with receipt PDF on success (Phase 6 wires the Mailable — leave as a stub call here)
GET /orders/{order}/confirmation → OrderController@confirmation
Reservation payment flow (table_and_food)
Same PaymentController@callback — detect whether reference belongs to Order or Reservation
On verified: Reservation.status → confirmed, Payment.status → paid (reservation_id set, order_id null)
Trigger ReservationConfirmationMail with the readable reservation number (Phase 6)
ReceiptService (implemented in this phase)
ReceiptService::generate(Order $order) — retrieve Order, OrderItem, Payment from database
Render Blade view as DomPDF PDF
Return PDF for attachment or streaming
No receipts table — generated on demand each time
GET /orders/{order}/receipt (signed, 24-hour expiry) → OrderController@receipt — stream PDF
Payment failure and hold expiry
Failed payment: Payment.status → failed, guest can retry the existing eligible pending order/reservation without creating a duplicate parent; clear only that flow's cart context after verified payment confirms it
Hold expiry: no action needed at callback time — availability query already excludes stale pending rows
Deliverable

Successful and failed FIUU payment flows work for both orders and reservations. ReceiptService generates PDFs on demand. Signed receipt link functional.

8. Phase 6 — Rasa AI and Email

Depends on: Phase 5 (payment triggers email), Phase 3 (Contact Us page exists)

Goal: all five Mailables sending correctly. Rasa AI works on its dedicated page with free-text preferences and the approved controlled allergen-exclusion input; deterministic mock mode remains the default.

OpenAiService
AiRecommender::recommend(array $items, array $preferences) — controller supplies database-filtered candidates; MockAiRecommender is default, OpenAiService is configurable
Validate API response — map each recommendation to a real MenuItem row, drop anything not found or not available
Return validated results
Failure: log error, return graceful fallback — Rasa AI is never a blocking dependency
Rasa AI recommendation endpoint
POST /ai/recommendations → AiRecommendationController@recommend (rate-limited via throttle middleware)
Validate bounded preferences_text and/or valid excluded_allergens[]; reject legacy customer-posted preference selectors; interpret supported text into internal filters and canonical allergen exclusions
Call AiRecommender::recommend($items, $preferences)
Return validated recommendations or fallback message
Rasa AI is not embedded on Contact Us; keep ContactController independent of recommendation providers.
Mailables (all five)
Mailable	Trigger	Key contents
OrderConfirmationMail	Payment verified → Order.status → confirmed (Phase 5 callback)	Order summary, PDF receipt attached, signed receipt link
ReservationConfirmationMail	Reservation.status → confirmed	Reservation details, readable reservation number, signed cancellation link
ReservationCancellationMail	Reservation.status → cancelled	Cancellation confirmation
GoogleReviewInvitationMail	Order or Reservation.status → completed	Optional link to configured external Google Review destination
ContactEnquiryMail	Contact form submitted	Guest name, email, message — sent to restaurant
Email is sent synchronously (no queue) for V1
Failed email is logged — does not roll back the triggering transaction
SMTP credentials from .env
Connect Phase 5 stubs
Replace stub calls in PaymentController@callback with real OrderConfirmationMail dispatch (with PDF attached)
Replace stub calls with real ReservationConfirmationMail dispatch using the readable reservation number.
Deliverable

Rasa AI recommendations work on the dedicated page only. All five Mailables send correctly. OrderConfirmationMail includes the receipt PDF.

9. Phase 7 — Admin Panel

Depends on: Phase 2 (models), Phase 5 (payment/order status), Phase 6 (email Mailables)

Goal: authenticated administrator can manage all restaurant operations from the admin panel.

Admin authentication
AdminSeeder provisions admin account (already seeded in Phase 2 — verify it works here)
GET /admin/login → Admin\AuthController@showLogin (uses guest middleware)
POST /admin/login → Admin\AuthController@login — Laravel auth, redirect to dashboard on success
POST /admin/logout → Admin\AuthController@logout
EnsureAdminRole middleware — checks auth()->user()->role === 'admin'
Apply auth + EnsureAdminRole to all /admin/* routes except login
Unauthenticated request → redirect to /admin/login
Non-admin authenticated request → 403
Dashboard
Admin\DashboardController@index — display: today's orders, today's reservations, today's revenue, pending orders, pending reservations, available tables
Orders
List orders, view details, view guest information, view ordered items, view order type
Update Order.status (pending → confirmed → preparing → ready → completed / cancelled)
View Payment.status — read-only, not editable
Order status and payment status clearly separated in UI
Reservations
List reservations, search by reservation_number
Confirm reservations, cancel reservations, mark completed
View guest_count, table_id, reservation_type, pre-ordered food
Adjust table assignment (re-assign to another table)
Cancelled or completed reservations clearly distinguished from active ones
Tables
List, create, edit tables
Toggle status between available and maintenance
Block maintenance toggle when future confirmed reservations depend on the table
Menu categories
List, create, edit categories
Delete blocked when menu_items reference the category — explain reason, provide next action
Menu items
List, create, edit items
Toggle is_available
Toggle is_featured
Update price, description, image, category, spice_level, protein_type, dietary_type, flavor_profile
Soft-delete (deactivate) rather than hard-delete — preserve historical records
Reports (stub — implemented in Phase 8)
Reports section visible but report generation wired in Phase 8
Deliverable

Authenticated administrator can manage orders, reservations, tables, and menu data. Unauthenticated requests redirect to login. Non-admin requests return 403.

10. Phase 8 — Reservation Identifiers, External Google Review Invitations, and Reports

Depends on: Phase 6 (email Mailables), Phase 7 (admin panel)

Goal: readable reservation identifiers, optional post-completion external Google Review invitations, and admin report generation all functional.

Reservation identifier
Generate a unique readable reservation_number when the reservation is created.
Display it in ReservationConfirmationMail, on the reservation confirmation page, and in admin records.
Use it for administrator lookup/check-in validation only; it is not a signed URL or a credential for protected actions.
External Google Review invitation
GoogleReviewInvitationMail is dispatched when an Order or Reservation first reaches `completed`.
The email links to the configured external Google Review destination and makes clear that review submission is optional.
Le Miting does not expose a feedback route or form, retain review data, submit a review, or call Google APIs. The link carries no internal transaction identifier.
Reports
Four report actions — one PDF per type/period:
Admin\ReportController@salesWeekly / @salesMonthly — sales data (orders, revenue, popular items)
Admin\ReportController@reservationsWeekly / @reservationsMonthly — reservation data (totals, guest count, popular times)
Each generates its own DomPDF PDF, downloaded from its own route
Report periods: rolling windows — weekly = last 7 days, monthly = last 30 days (PRD §33)
No daily reports — not in PRD scope
Deliverable

Reservation numbers displayed in confirmation emails, confirmation pages, and admin records. Completed transactions may receive an optional external Google Review invitation. Four weekly/monthly sales and reservation PDF reports are downloadable from the admin panel.

11. Phase 9 — Testing

Depends on: all previous phases complete and functionally working

Goal: three separate testing concerns are addressed with the appropriate tool for each.

11.1 Laravel Feature and Unit Tests

Use Laravel's built-in testing framework (Pest or PHPUnit, whichever is configured in the project).

Unit tests — business logic:

text
OrderService
├── Total calculated from database prices (not client input)
├── Cart items verified as is_available before order creation
└── item_name and unit_price snapshots correctly stored

ReservationService
├── Availability check correctly detects overlapping 120-minute windows
├── Stale pending rows (>15 min) excluded from availability check
├── DB transaction prevents concurrent double-booking
└── guest_count validated against table capacity

FiuuPaymentService
├── Callback signature verified before any status update
├── Duplicate callback (same provider_reference) updates existing row — no duplicate created
├── Payment belongs to either order_id or reservation_id — never both
├── Amount verified against order/reservation total
└── Admin can set `refunded` only after actual FIUU refund processed — no other status manually settable

Signed URLs
├── Valid signed URL allows access
├── Tampered signature denied
├── Expired link denied
└── Raw ID access (no signature) denied

Feature tests — application flows:

text
Guest order flow
├── Cart accepts available items
├── Cart rejects unavailable items
├── Total calculated server-side
├── Order created with correct status
└── FIUU callback updates order and payment status

Reservation flow
├── table_only confirmed immediately
├── table_and_food created as pending until payment verified
├── Overlapping reservation rejected
├── Concurrent booking (race condition) — only one succeeds
└── 15-minute hold: slot freed after expiry

External Google Review invitation
├── Sent only after the first completed transition
├── Uses the configured external destination
└── Does not create local review storage or a signed review route

Admin authorization
├── Unauthenticated request → redirect to login
└── Non-admin authenticated user → 403
11.2 End-to-End Browser Tests (Playwright)

Cover complete user journeys through the browser.

Guest journeys:

text
Browse menu → search → filter → add to cart → checkout → FIUU payment → order confirmation → receipt
Table-only reservation → availability → select table → confirm → reservation number → email
Table + food reservation → availability → pre-order food → FIUU payment → confirm → reservation number
Cancel reservation via signed link
Completed order or reservation → optional external Google Review invitation email
Contact Us → fill form → submit → success message
Rasa AI → enter preferences → receive recommendations → add to cart

Admin journeys:

text
Login → dashboard
View orders → update order status
View reservations → confirm / cancel / complete
Manage tables → toggle maintenance
Add / edit / deactivate menu item
Generate weekly sales report → PDF downloaded
Generate monthly sales report → PDF downloaded
Generate weekly reservations report → PDF downloaded
Generate monthly reservations report → PDF downloaded

Edge cases:

text
Empty cart → checkout blocked
Unavailable item in cart → checkout blocked
No tables available → appropriate message shown
FIUU payment failure → retry shown
Rasa AI failure → fallback message shown, menu browsable
Expired signed link → generic "link no longer valid"
Unauthenticated admin access → redirect to login
11.3 Security Testing (Strix)

Strix is used for security assessment — it is not a replacement for Playwright or Laravel tests.

Focus areas:

text
Admin authentication — brute force protection, credential handling
EnsureAdminRole — authorization enforcement
Signed URL integrity — tamper and replay resistance
Input handling — injection, XSS surface
CSRF protection — state-changing request protection
Payment callback — spoofed callback rejection
API key exposure — confirm OpenAI key not visible client-side
FIUU credentials — not exposed in source or frontend
Common web vulnerabilities

Strix findings must be resolved before proceeding to Phase 10.

12. Phase 10 — Security Hardening

Depends on: Phase 9 (testing and Strix assessment complete)

Goal: verify every security requirement before deployment. Nothing ships without this checklist passing.

Pre-deployment security checklist
text
Environment
☐ .env excluded from Git
☐ .env.example has no real credentials
☐ vendor/ and node_modules/ excluded from Git
☐ APP_DEBUG=false in production .env
☐ APP_ENV=production in production .env

Credentials
☐ OpenAI API key in .env only — not in source, not in Blade, not in JS
☐ FIUU credentials in .env only
☐ SMTP credentials in .env only
☐ No secrets committed to Git history

Authentication and authorization
☐ All /admin/* routes (except /admin/login) require auth + EnsureAdminRole
☐ Unauthenticated requests → redirect to /admin/login
☐ Non-admin authenticated requests → 403
☐ Admin passwords stored as bcrypt hashes — not plaintext

Input and data
☐ Server-side validation on all forms (Form Requests in place)
☐ No raw SQL from user input — Eloquent ORM used throughout
☐ Mass assignment protected — $fillable defined on all models
☐ CSRF middleware active on all state-changing requests

Payment
☐ FIUU callback signature verified before any status update
☐ Browser redirect alone does not confirm payment
☐ Duplicate callback handled idempotently (provider_reference unique constraint)
☐ Admin cannot manually set payment status

Guest access
☐ Signed URL validation active on all signed routes
☐ All three signed URLs expire after 24 hours (`now()->addHours(24)`)
☐ Tampered or expired signed URLs return generic error — no detail exposed
☐ No fallback to raw ID access when signature is missing

Error handling
☐ Error pages do not expose stack traces, SQL errors, or API keys
☐ Rasa AI errors return user-friendly fallback — no exception details exposed
☐ Contact form failure returns error — no false success

Infrastructure
☐ HTTPS configured and enforced in production
☐ Production database credentials separate from development credentials
Deliverable

Every checklist item passes. Any Strix finding from Phase 9 is resolved.

13. Phase 11 — Deployment

Depends on: Phase 10 (security hardening complete)

Goal: application running correctly in production on Exabytes with all integrations live.

Deployment steps
text
Local development complete
      ↓
Push to Git / GitHub
      ↓
Configure Exabytes production server
├── PHP version compatible with Laravel version
├── Apache configured (document root → public/)
├── MySQL database created
└── SSL certificate configured (HTTPS)
      ↓
Deploy application files to server
      ↓
Set production .env
├── APP_ENV=production
├── APP_DEBUG=false
├── APP_URL (production URL)
├── DB_* (production database)
├── MAIL_* (production SMTP)
├── FIUU credentials
├── OPENAI_API_KEY
└── APP_KEY
      ↓
Run migrations (php artisan migrate --force)
      ↓
Run seeders (AdminSeeder, MenuCategorySeeder, MenuItemSeeder, TableSeeder)
      ↓
Set file storage permissions (storage/, bootstrap/cache/)
      ↓
Run php artisan config:cache and php artisan route:cache
      ↓
Production smoke tests
├── Landing page loads
├── Menu loads
├── Cart works
├── Checkout reaches FIUU
├── FIUU payment callback reachable from FIUU servers
├── Reservation form works
├── Admin login works
├── Contact Us form sends email
├── Rasa AI returns recommendations
├── Signed links work (receipt, cancellation)
└── HTTPS enforced
Deliverable

Application running in production. All integrations live. HTTPS enforced. Admin seeded. Production smoke tests pass.

14. Engineering Milestones
Milestone	Completion criteria
M1	Laravel project configured, packages installed, .env set, Git initialised
M2	All migrations run, models defined with relationships, seeders produce valid data
M3	Guest can browse menu, manage cart, reach checkout, and submit a contact enquiry
M4	Guest can check table availability and create both reservation types
M5	FIUU payment flows work for orders and reservations; ReceiptService generates PDFs
M6	Rasa AI returns recommendations on its dedicated page; all five Mailables sending
M7	Authenticated administrator can manage orders, reservations, tables, and menu data
M8	Reservation numbers displayed consistently, optional Google Review invitations work, all 4 report PDFs downloadable
M9	Laravel tests pass, Playwright journeys pass, Strix findings resolved
M10	Pre-deployment security checklist complete — every item passes
M11	Application live in production, HTTPS enforced, smoke tests pass
15. Definition of Done

A feature is complete when all of the following are true:

text
☐ Backend logic implemented
☐ Required database changes implemented (migration exists)
☐ Input validation implemented (Form Request in place)
☐ Server-side calculation used where relevant — client input not trusted for totals or status
☐ Success, loading, empty, and failure states handled
☐ Authorization and security requirements implemented
☐ Relevant Laravel feature/unit tests pass
☐ Relevant Playwright browser tests pass
☐ The feature works correctly in the actual application flow (not just in isolation)
☐ Naming rules followed: FIUU, Rasa AI, admin, guest
16. Final Engineering Workflow
text
PRD.md
"What is required"
      ↓
APP_FLOW.md
"How users move through it"
      ↓
UI_UX_BRIEF.md
"What the experience looks and feels like"
      ↓
BACKEND_DESIGN.md
"How the server implements it"
      ↓
ENGINEERING_PLAN.md
"In what order it gets built and how it is verified"
      ↓
Implementation (Phases 1–8)
      ↓
Testing (Phase 9)
      ↓
Security Hardening (Phase 10)
      ↓
Deployment (Phase 11)
      ↓
Production Verification

PRD defines what is required. App Flow defines how users move through it. UI/UX Brief defines the experience. Backend Design defines the server implementation. Engineering Plan defines the build order and verification criteria. The documents form a dependency chain — each one is grounded in the ones before it.

---

## REC-02B — Delivery and verification boundary

Implement normalized controlled allergen vocabulary and per-item assessments without inferred menu facts. Test admin authorization, provenance, independent unknown statuses, unique/FK/CHECK constraints, transactional rollback, stale forms, relevant-edit invalidation, manual supplier/preparation invalidation, no automatic expiry and preservation of Rasa AI allergy blocking.

Run focused tests only through the guarded le_miting_test bootstrap. Do not migrate development/demo, populate restaurant assessments, change catalog records or deploy to the isolated demo in this milestone. Preserve the demo migration guard's original chain fingerprint; new migrations are not implicitly approved for demo.

REC-02C is approved as a constrained customer-facing exclusion feature. A current documented admin assessment governs when a row exists; deterministic menu-text analysis is used only when no row exists. Menu wording is not restaurant verification and does not establish ingredient or shared-kitchen safety. No allergen-free or allergy-safe promises are permitted.

## REC-02C — Approved customer-facing exclusions

Scope: on `/ai/recommendations`, accept `preferences_text` (optional, maximum 300 characters) and/or controlled `excluded_allergens[]` for the ten canonical codes (`peanut`, `tree_nuts`, `milk`, `egg`, `soy`, `wheat_gluten`, `fish`, `crustacean_shellfish`, `molluscs`, `sesame`); at least one non-blank text value or valid code is required. Legacy structured preference selectors remain prohibited. Map explicit supported English/Malay terms to codes and merge/deduplicate with checkbox values. Generic/unspecified allergy or intolerance, and unmapped allergen/intolerance requests, fail closed with a staff-confirmation notice and zero provider calls.

Eligibility is enforced before and after provider selection. Candidates are available menu items in active categories. For each requested code, a present admin assessment is authoritative and qualifies only when current for the item, `reviewed_absent` for recipe status, `reviewed_no_known_risk` for cross-contact status, and complete in reviewer, UTC timestamp, and evidence. Any stale, invalidated, incomplete, unknown, contains, or possible assessment excludes the item. If there is no row for that item/code, deterministic English/Malay keyword matching on name and description excludes positive matches and ignores explicit negated phrases; a non-match qualifies only for this limited menu check. Revalidate returned provider IDs with the same rule. Empty candidates mean zero provider calls.

Results must include the shared-kitchen caution and tell customers to inform staff before ordering. Show `Reviewed: [allergen] not listed in recipe · No known cross-contact risk` for a qualifying current row and `Menu check: [allergen] not listed in dish name or description · Confirm with staff` when no row exists. Menu text does not verify ingredients or cross-contact and must not be presented as assessment provenance. Do not claim “allergy-safe”, “allergen-free”, “100% safe”, or equivalent in UI, badges, or provider prompts.

Acceptance evidence for REC-02C: focused tests prove input bounds/combination, canonical code validation, English/Malay mapping and deduplication, generic/unmapped fail-closed behavior with zero provider calls, no-row menu matching and negation handling, current-assessment overrides, stale/invalidation exclusion, pre-provider eligibility, post-provider revalidation, zero-candidate behavior, truthful UI badges/caution, and preservation of non-allergy recommendations. Use guarded test data and do not fabricate restaurant assessments. Empty assessment tables are supported through the menu-text fallback; admin review rows remain authoritative when present. The isolated demo migration chain remains separately governed and is not implicitly authorized by this specification approval.
