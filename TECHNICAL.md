Le Miting — Technical Design

> This document answers: **what is the architecture, stack, infrastructure, integrations, security model, and major technical decisions?**
>
> Product requirements → `PRD.md`
> Screen and system flow → `APP_FLOW.md`
> Visual design and UI behaviour → `UI_UX_BRIEF.md`
> Database, models, APIs, auth details → `BACKEND_DESIGN.md`
> Build order, dependencies, testing, done criteria → `ENGINEERING_PLAN.md`

---

## 1. Technical Overview

Le Miting is a server-rendered web application built with Laravel. It provides restaurant menu browsing, guest food ordering, table reservation, food pre-ordering, FIUU payment, PDF receipts, customer feedback, AI menu recommendations, and an administrator management panel.

The application follows a centralised Laravel MVC architecture. Laravel handles routing, business logic, authentication, session management, signed URLs, database access, external integrations, and email. The frontend is rendered server-side using Blade and Tailwind CSS.

Customers access the system as guests — no account or login required. Administrators authenticate through a dedicated login to access the protected admin panel. Certain guest actions (receipt download, reservation cancellation, feedback form) are secured with Laravel signed URLs instead of accounts.

---

## 2. Technology Stack

| Component | Technology |
|---|---|
| Framework | Laravel |
| Language | PHP |
| Frontend rendering | Laravel Blade |
| CSS | Tailwind CSS |
| Database | MySQL |
| Web server | Apache |
| Hosting | Exabytes |
| AI integration | OpenAI API |
| Payment gateway | FIUU |
| PDF generation | Laravel DomPDF (`barryvdh/laravel-dompdf`) |
| Email | Laravel Mail |
| QR code generation | `simplesoftwareio/simple-qrcode` |
| Version control | Git + GitHub |
| Development environment | VS Code |

No client-side JavaScript framework (React, Vue, Livewire) is used. Client-side JavaScript is limited to progressive enhancement — cart quantity UI, form interactions, and the Rasa AI widget.

---

## 3. System Architecture

```text
                        CUSTOMER / ADMIN
                               |
                          Web Browser
                               |
                         Apache (Exabytes)
                               |
                          Laravel App
                    /      |      |      \
                   /       |      |       \
                  v        v      v        v
              Blade     Business  MySQL  External
           + Tailwind    Logic           Services
                          |          /    |    \
                          |      OpenAI  FIUU  Mail
                          v
                      Admin Panel
                          |
                     Administrator
```

The Laravel application is the single deployable unit. It connects the customer-facing interface, the admin panel, the MySQL database, and all external services. There are no microservices, no separate API server, and no client-side framework.

---

## 4. Request Lifecycle

```text
Browser request
      |
   Apache
      |
  Laravel Router (web.php)
      |
  Middleware (auth, signed URL, CSRF, throttle)
      |
  Controller
      |
  Service / Model (business logic, DB access)
      |
  External service (if required — OpenAI, FIUU, Mail)
      |
  Blade view
      |
  Browser response
```

Administrator requests follow the same lifecycle with `auth` + `EnsureAdminRole` middleware applied before any admin controller runs.

---

## 5. Routing

All routes are defined in `routes/web.php`. There is no `api.php` — no external API consumer exists.

Routes are grouped into three categories:

**Public customer routes** — no auth middleware:
- Homepage, menu, search, filters
- Cart and checkout
- Order and payment flow
- Reservation flow
- Contact Us and enquiry form submission
- Rasa AI recommendation endpoint (rate-limited)

**Signed URL guest routes** — no auth, Laravel signed URL required:
- Receipt download (`/orders/{order}/receipt`)
- Reservation cancellation confirmation
- Feedback form (bound to a specific order or reservation)

**Admin routes** — `auth` + `EnsureAdminRole` middleware:
- `/admin/login`
- `/admin/dashboard`
- `/admin/orders`
- `/admin/reservations`
- `/admin/tables`
- `/admin/menu/categories`
- `/admin/menu/items`
- `/admin/feedback`
- `/admin/reports`

---

## 6. Application Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── MenuController.php
│   │   ├── CartController.php
│   │   ├── OrderController.php
│   │   ├── CheckoutController.php
│   │   ├── ReservationController.php
│   │   ├── FeedbackController.php
│   │   ├── AiRecommendationController.php
│   │   ├── ContactController.php
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── OrderController.php
│   │       ├── ReservationController.php
│   │       ├── TableController.php
│   │       ├── MenuCategoryController.php
│   │       ├── MenuItemController.php
│   │       ├── FeedbackController.php
│   │       └── ReportController.php
│   └── Middleware/
│       └── EnsureAdminRole.php
├── Models/
│   ├── User.php
│   ├── MenuCategory.php
│   ├── MenuItem.php
│   ├── Table.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── Payment.php
│   ├── Reservation.php
│   ├── ReservationFood.php
│   └── Feedback.php
├── Mail/
│   ├── OrderConfirmationMail.php
│   ├── ReservationConfirmationMail.php
│   ├── ReservationCancellationMail.php
│   ├── FeedbackInvitationMail.php
│   └── ContactEnquiryMail.php
└── Services/
    ├── OpenAiService.php
    ├── FiuuPaymentService.php
    ├── ReservationService.php
    └── ReceiptService.php

resources/
└── views/
    ├── layouts/
    ├── components/
    ├── customer/
    │   ├── home/
    │   ├── menu/
    │   ├── cart/
    │   ├── checkout/
    │   ├── orders/
    │   ├── reservations/
    │   ├── feedback/
    │   └── contact/
    └── admin/
        ├── dashboard/
        ├── orders/
        ├── reservations/
        ├── tables/
        ├── menu/
        └── reports/
```

Business logic belongs in Service classes. Controllers are thin — they receive the request, call a service, and return a view or redirect. No business logic goes into Blade templates or route closures.

---

## 7. Database

MySQL is the database. Laravel Eloquent ORM is the primary interface.

Tables:

```text
users
menu_categories
menu_items
tables
orders
order_items
payments
reservations
reservation_foods
feedback
```

`menu_items` uses soft deletes so historical `order_items` and `reservation_foods` records remain valid after a menu item is deactivated.

`order_items.item_name`, `order_items.unit_price`, `reservation_foods.item_name`, and `reservation_foods.unit_price` are stored as snapshots at transaction time. Renaming or repricing a menu item never alters historical records.

Full schema — columns, types, indexes, constraints, and relationships — is defined in `BACKEND_DESIGN.md`.

---

## 8. Customer Access Model

Customers do not register or log in. All customer-facing pages are publicly accessible.

Protected guest actions use Laravel signed URLs (`URL::temporarySignedRoute`):

| Action | Mechanism |
|---|---|
| Receipt download | Signed URL emailed with order confirmation |
| Reservation cancellation | Signed URL emailed with reservation confirmation |
| Feedback form | Signed URL emailed with feedback invitation |

A missing, tampered, or expired signature returns a generic "link no longer valid" error — never a hint about which part failed, never a fallback to raw ID access.

Guest sessions are used for cart state during a visit. This is ordinary session usage — it is not authentication.

---

## 9. Administrator Authentication and Authorization

The admin panel is protected by Laravel's built-in session-based authentication.

- Table: `users` (`role = admin`)
- Middleware: `auth` + `EnsureAdminRole` on all `/admin/*` routes
- Login page: `/admin/login` (uses `guest` middleware to redirect already-authenticated admins)
- Single role only — no staff tiers, no permission packages
- Accounts are provisioned directly (seeder or manual) — no self-registration

Password reset is supported via Laravel's standard password reset flow. No email verification is required (admin accounts are provisioned, not self-registered).

---

## 10. Reservation Architecture

Two reservation types:

| Type | Payment | Initial status |
|---|---|---|
| `table_only` | None | `confirmed` immediately |
| `table_and_food` | FIUU required | `pending` until payment verified |

Availability rule: a reservation occupies its assigned table for a fixed 120-minute window (`reservation_time` through `reservation_time + 120 minutes`). No two reservations may overlap on the same table during that window.

Availability is checked inside a database transaction with row locking so concurrent requests cannot both succeed for the same slot.

**15-minute payment hold (table_and_food only):** a `pending` reservation blocks its slot immediately. If FIUU payment is not verified within 15 minutes of creation, the availability query excludes that stale `pending` row from overlap checks going forward — freeing the slot without requiring a scheduled job.

QR code: generated via `simplesoftwareio/simple-qrcode` after confirmation. The QR payload is the `reservation_number`. It is used strictly for check-in validation — not for feedback, cancellation, or any other action.

---

## 11. Order Architecture

Two order types: `dine_in`, `takeaway`.

Each order captures `customer_name`, `customer_email`, `customer_phone`, `order_number`, `order_type`, `status`, `total`, and `notes`.

`total` is the single final payable amount. No subtotal, discount, or tax fields — price adjustments are outside V1 scope.

`order_items` snapshot `item_name` and `unit_price` at order time. Historical records are unaffected by subsequent menu changes.

---

## 12. Payment Integration (FIUU)

FIUU is the payment gateway for:
- Standalone food orders
- `table_and_food` reservations

Payment lifecycle:

```text
Laravel creates pending Order or Reservation
      |
Redirect to FIUU hosted payment page
      |
Customer completes payment
      |
FIUU sends server-to-server callback to Laravel
      |
Laravel verifies callback signature + amount + reference
      |
Payment row upserted (idempotent on provider_reference)
      |
Order/Reservation status updated
      |
Confirmation email sent
```

Rules:
- The browser redirect back from FIUU is never treated as proof of payment. Only the verified server-to-server callback updates order/reservation status.
- `payments.provider_reference` has a unique constraint — duplicate callbacks update the existing row rather than creating a second payment.
- `payments.status` values: `pending`, `paid`, `failed`, `refunded`.
- Payment status is written only by the verified FIUU callback. Admin views payment status as read-only.
- FIUU credentials are stored in `.env`, never in source code.

---

## 13. Rasa AI (OpenAI Integration)

OpenAI API powers Rasa AI, Le Miting's optional menu recommendation feature. Rasa AI appears on two surfaces:
- The Rasa AI page (FR-17)
- The Contact Us page (FR-17b)

Both surfaces use the same `OpenAiService` and identical logic — there is no second integration.

Flow:

```text
Customer enters preferences
      |
Laravel retrieves currently available menu items
      |
OpenAiService sends items + preferences to OpenAI API
      |
OpenAI returns recommendation response
      |
Laravel validates response — maps to real menu_items rows (is_available = true)
      |
Items not matching a real available menu item are dropped
      |
Validated recommendations displayed to customer
```

The AI may only recommend items that exist in the database and are currently available. The OpenAI API key is stored in `.env` and never exposed in frontend code or client-side JavaScript. The feature is optional — failure falls back gracefully without breaking the page.

---

## 14. Email Integration (Laravel Mail)

Five confirmed Mailables:

| Mailable | Trigger | Contains |
|---|---|---|
| `OrderConfirmationMail` | Order placed (`pending`) | Order summary, signed receipt link |
| `ReservationConfirmationMail` | Reservation confirmed | Reservation details, QR code, signed cancellation link |
| `ReservationCancellationMail` | Reservation cancelled | Cancellation confirmation |
| `FeedbackInvitationMail` | Order or reservation → `completed` | Signed feedback form link |
| `ContactEnquiryMail` | Contact Us form submitted | Customer name, email, message — sent to the restaurant |

No `PaymentConfirmationMail` exists as a separate Mailable. The PDF receipt is attached to `OrderConfirmationMail` (generated on demand by `ReceiptService` after payment is verified). `ReservationConfirmationMail` includes the QR code.

Email is sent synchronously (no queue) for V1. At this scale, inline sending adds negligible response time. Switch to Laravel's `database` queue driver if email latency becomes measurable — not Redis.

SMTP credentials are stored in `.env`, never committed to Git.

---

## 15. PDF Generation

Library: `barryvdh/laravel-dompdf`.

PDF use cases:

| Document | Trigger | Recipient |
|---|---|---|
| Order receipt | Payment verified — generated on demand | Customer (attached to `OrderConfirmationMail`, available via signed URL) |
| Weekly report | Admin requests | Admin (downloaded from admin panel) |
| Monthly report | Admin requests | Admin (downloaded from admin panel) |

Receipts are generated fresh from `orders`/`order_items`/`payments` each time — nothing is persisted to disk. There is no `receipts` table.

---

## 16. QR Code Generation

Library: `simplesoftwareio/simple-qrcode`.

QR codes are generated server-side after a reservation is confirmed. The payload is the `reservation_number` string. The image is embedded in the `ReservationConfirmationMail` and displayable on the reservation confirmation page.

The QR code is a convenience for check-in validation only. It does not carry signed URL data, feedback links, or any other action. The reservation record in the database remains the authoritative source.

---

## 17. Customer Feedback and Google Review Redirect

Feedback is stored in the Laravel application's `feedback` table.

Flow:

```text
Order / Reservation → completed
      |
FeedbackInvitationMail sent (signed link)
      |
Customer opens feedback form
      |
Feedback saved to MySQL
      |
Thank-you page with optional Google Review link
      |
Customer clicks link → Google Review page opens in new tab
```

The Google integration is a static link to the restaurant's Google review page. No Google Places API is used. No review data is sent to or retrieved from Google. The link does not carry feedback data.

---

## 18. Contact Us

The Contact Us page provides:
- Restaurant contact information (address, phone, email, operating hours)
- An enquiry form (name, email, message) that triggers `ContactEnquiryMail` to the restaurant
- The Rasa AI recommendation widget (same `OpenAiService` used on the main AI page)

The enquiry form does not create any database record. It is a one-way email to the restaurant. No reply system is in scope.

---

## 19. Menu Item Availability and Historical Data

Menu items have an `is_available` boolean. Setting `is_available = false` hides the item from new orders and from AI recommendations. It does not delete the item.

`menu_items` uses soft deletes (`deleted_at`). This preserves the foreign key reference from historical `order_items` and `reservation_foods` rows — receipts and order history remain accurate even after an item is fully removed.

---

## 20. Security

| Concern | Mechanism |
|---|---|
| Admin authentication | Laravel session-based auth |
| Admin authorization | `EnsureAdminRole` middleware on all `/admin/*` routes |
| Guest protected actions | Laravel signed URLs (`URL::temporarySignedRoute`) |
| Password storage | Laravel default password hashing (bcrypt) |
| CSRF | Laravel CSRF middleware on all state-changing web requests |
| Input validation | Form Requests — validated before any processing or DB write |
| SQL injection | Eloquent ORM and parameterized queries — no raw SQL from user input |
| Credentials | `.env` only — never in source code, never committed to Git |
| Payment callback trust | Server-to-server signature verification — browser redirect alone is not trusted |
| AI output | Server-side validation — AI responses mapped to real `menu_items` rows before rendering |
| HTTPS | Required in production on Exabytes |
| Rate limiting | Applied to AI recommendation endpoint and admin login |

---

## 21. External Service Boundaries

```text
         Laravel Application
               |
     ┌─────────┼──────────┐
     |         |          |
     v         v          v
  OpenAI      FIUU    Laravel Mail
  (AI recs)  (payment) (SMTP provider)

         Laravel Application
               |
               v
       simplesoftwareio
       /simple-qrcode
       (server-side only)

         Laravel Application
               |
               v
       Static Google Review link
       (no API, no data exchange)
```

External service credentials are stored in `.env`. The application does not reproduce or proxy external service functionality beyond what is required.

---

## 22. Hosting and Infrastructure

```text
Internet
   |
Exabytes Hosting
   |
   ├── Apache (web server)
   ├── PHP / Laravel
   ├── MySQL
   └── Application files
```

Production environment variables are set on the server, not in committed files. The exact PHP version, MySQL version, and SSL configuration follow Exabytes hosting requirements.

---

## 23. Backup and Recovery

| Asset | Mechanism |
|---|---|
| Source code | Git + GitHub |
| Database | MySQL backup procedures on Exabytes |
| Configuration | `.env` kept separate from Git, backed up securely |

Sensitive credentials must not be stored in the Git repository.

---

## 24. Version Control

Git for local version control. GitHub as the remote repository.

`.gitignore` must exclude:
- `.env`
- `vendor/`
- `node_modules/`
- Storage files not required in the repository

---

## 25. Development Environment

- VS Code
- Local Laravel development environment (Laravel Herd or equivalent)
- Local MySQL database
- Git + GitHub

Local `.env` credentials must not be used in production.

---

## 26. Technical Decisions

| ID | Decision | Rationale |
|---|---|---|
| TD-01 | Laravel | Routing, auth, validation, DB, mail, security — everything required, one framework |
| TD-02 | Blade + Tailwind CSS | Server-rendered app; no client-side framework justified |
| TD-03 | MySQL | Relational data; Eloquent ORM; Exabytes compatible |
| TD-04 | Guest customer access | No accounts removes registration friction for V1 |
| TD-05 | Laravel signed URLs | Secure guest access without accounts |
| TD-06 | FIUU | Required payment gateway for this project |
| TD-07 | OpenAI API | AI menu recommendations — single integration, two surfaces (AI page + Contact Us) |
| TD-08 | DomPDF | Server-side PDF; PRD-specified; sufficient for receipt and report layouts |
| TD-09 | Laravel Mail | Transactional email; synchronous for V1 |
| TD-10 | `simplesoftwareio/simple-qrcode` | Blade-friendly, server-side QR generation; no JS dependency |
| TD-11 | Apache on Exabytes | Hosting environment requirement |
| TD-12 | Single admin role | No multi-tier staff requirement in PRD |
| TD-13 | Static Google Review link | No API needed — a direct link achieves the requirement |
| TD-14 | Menu item soft deletes | Preserves historical order/reservation integrity when items are removed |
| TD-15 | `total` only (no subtotal) | Discounts and taxes are out of V1 scope |
| TD-16 | No receipt persistence | Receipts generated on demand from live DB data; no `receipts` table needed |
| TD-17 | Synchronous email | V1 scale does not justify a queue worker; switch to `database` queue if needed |
| TD-18 | No Redis | No evidence of need; add only if queue or cache requirements arise |

---

## 27. Technical Constraints

- Laravel is the application framework. It cannot be replaced.
- PHP is the backend language.
- Blade is the frontend rendering technology. No React, Vue, or Livewire.
- Tailwind CSS is the CSS framework.
- MySQL is the database.
- FIUU is the payment gateway.
- OpenAI API is the AI service.
- `simplesoftwareio/simple-qrcode` is the QR library.
- DomPDF is the PDF library.
- Laravel Mail handles all application email.
- Apache is the production web server (Exabytes).
- Customers do not require accounts.
- Admin functions require authentication and the `admin` role.
- Guest protected actions use signed URLs.
- Reservation duration is fixed at 120 minutes.
- Payment holds expire after 15 minutes.
- AI recommendations use only available, real `menu_items` rows.
- Google review uses a static link — no Google API.
- No scheduled jobs are required for correctness (the 15-minute hold is enforced by query logic, not a cron).