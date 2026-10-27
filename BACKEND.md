# Le Miting — Backend Design

> This document answers: **what data exists, how is it structured, who can access it, what routes exist, and how do authentication and authorization work?**
>
> Product requirements → `PRD.md`
> Architecture and integrations → `TECHNICAL_DESIGN.md`
> Screen and system flow → `APP_FLOW.md`
> Visual design and UI behaviour → `UI_UX_BRIEF.md`
> Build order and done criteria → `ENGINEERING_PLAN.md`

---

## Naming Rules

| Concept | Correct name |
|---|---|
| Payment gateway | FIUU |
| AI feature | Rasa AI |
| Admin role | admin / administrator |
| Customer | guest |

---

## 1. Backend Overview

Le Miting uses Laravel as the backend framework. Laravel handles routing, business logic, database operations, authentication, authorization, validation, payment processing, email notifications, PDF generation, AI integration, and access control.

```text
Guest / Admin
      │
      ▼
   Laravel
      │
┌─────┼──────────────────────────────┐
│     │        │        │      │     │
Routes  Controllers  Services  Auth  Validation
│     │        │        │      │
└─────┴────────┴────────┴──────┘
               │
               ▼
           Eloquent ORM
               │
               ▼
             MySQL

External Services
├── FIUU          (payment gateway)
├── OpenAI API    (Rasa AI recommendations)
├── SMTP          (Laravel Mail)
└── Google        (static review link only — no API)
```

---

## 2. Backend Responsibilities

- Process guest food orders
- Process table reservations (table_only and table_and_food)
- Check table availability with 120-minute duration and row-locking
- Manage the 15-minute payment hold for table_and_food reservations
- Manage cart and order data (session-based cart)
- Process FIUU payments and verify server-to-server callbacks
- Generate receipts on demand via DomPDF
- Generate unique readable reservation numbers
- Send confirmation, cancellation, and optional Google Review invitation emails
- Provide Rasa AI menu recommendations via OpenAI API
- Process Contact Us enquiries
- Protect guest resources through signed URLs
- Manage administrator authentication and authorization
- Manage menus, categories, tables, orders, and reservations
- Generate weekly and monthly PDF reports

---

## 3. Application Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── HomeController.php
│   │   ├── MenuController.php
│   │   ├── CartController.php
│   │   ├── CheckoutController.php
│   │   ├── OrderController.php
│   │   ├── ReservationController.php
│   │   ├── PaymentController.php
│   │   ├── ContactController.php
│   │   ├── AiRecommendationController.php
│   │   └── Admin/
│   │       ├── DashboardController.php
│   │       ├── OrderController.php
│   │       ├── ReservationController.php
│   │       ├── TableController.php
│   │       ├── MenuCategoryController.php
│   │       ├── MenuItemController.php
│   │       └── ReportController.php
│   ├── Middleware/
│   │   └── EnsureAdminRole.php
│   └── Requests/
│       ├── StoreOrderRequest.php
│       ├── StoreReservationRequest.php
│       ├── StoreContactRequest.php
│       ├── UpdateMenuItemRequest.php
│       └── UpdateTableRequest.php
│
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
│
├── Mail/
│   ├── OrderConfirmationMail.php
│   ├── ReservationConfirmationMail.php
│   ├── ReservationCancellationMail.php
│   ├── GoogleReviewInvitationMail.php
│   └── ContactEnquiryMail.php
│
└── Services/
    ├── FiuuPaymentService.php
    ├── OpenAiService.php
    ├── ReservationService.php
    └── ReceiptService.php

database/
├── migrations/
└── seeders/
    └── AdminSeeder.php    ← provisions the initial admin account

routes/
└── web.php
```

Controllers are thin — they receive the request, call a service or model, and return a view or redirect. Business logic belongs in Services.

---

## 4. Route Table

All routes are defined in `routes/web.php`. There is no `api.php`.

**Middleware key:**

| Key | Applied middleware |
|---|---|
| Public | None — accessible by any visitor |
| Signed | `signed` — Laravel signed URL required; invalid or expired signature → generic error |
| Auth + Admin | `auth` + `EnsureAdminRole` — authenticated admin session required |

### 4.1 Public Routes

| Method | Path | Controller@Action | Purpose |
|---|---|---|---|
| GET | `/` | `HomeController@index` | Landing page |
| GET | `/menu` | `MenuController@index` | Browse menu with categories, search, filters |
| GET | `/menu/{item}` | `MenuController@show` | View single menu item |
| GET | `/order` | `OrderController@create` | Start ordering (entry point) |
| GET | `/cart` | `CartController@index` | View cart |
| POST | `/cart` | `CartController@store` | Add item to cart |
| PATCH | `/cart/{item}` | `CartController@update` | Update cart item quantity |
| DELETE | `/cart/{item}` | `CartController@destroy` | Remove item from cart |
| DELETE | `/cart` | `CartController@clear` | Clear entire cart |
| GET | `/checkout` | `CheckoutController@create` | Display checkout form |
| POST | `/checkout` | `CheckoutController@store` | Validate + create order → redirect to FIUU |
| GET | `/payment/return` | `PaymentController@return` | FIUU browser redirect return (display pending state only — not proof of payment) |
| POST | `/payment/callback` | `PaymentController@callback` | FIUU server-to-server callback — verify + update payment/order status |
| GET | `/orders/{order}/confirmation` | `OrderController@confirmation` | Order confirmation page (shown after verified payment) |
| GET | `/reservation` | `ReservationController@create` | Display reservation type selection |
| POST | `/reservation/availability` | `ReservationController@availability` | Check table availability for requested slot |
| POST | `/reservation` | `ReservationController@store` | Create reservation → immediate confirm (table_only) or redirect to FIUU (table_and_food) |
| GET | `/reservation/{reservation}/confirmation` | `ReservationController@confirmation` | Reservation confirmation page |
| POST | `/ai/recommendations` | `AiRecommendationController@recommend` | Generate Rasa AI menu recommendations (rate-limited) |
| GET | `/contact` | `ContactController@create` | Display Contact Us page |
| POST | `/contact` | `ContactController@store` | Submit contact enquiry → send ContactEnquiryMail |

### 4.2 Signed URL Routes (Guest Protected)

| Method | Path | Controller@Action | Purpose |
|---|---|---|---|
| GET | `/orders/{order}/receipt` | `OrderController@receipt` | Generate + stream PDF receipt |
| GET | `/reservation/{reservation}/cancel` | `ReservationController@cancelForm` | Display cancellation confirmation form |
| POST | `/reservation/{reservation}/cancel` | `ReservationController@cancel` | Process cancellation → send ReservationCancellationMail |

> Signed route note: Laravel's `signed` middleware validates the URL signature before the controller runs. An invalid, tampered, or expired signature returns a generic "this link is no longer valid" response — the controller never executes.

### 4.3 Admin Routes (Auth + EnsureAdminRole)

All admin routes are prefixed `/admin`.

| Method | Path | Controller@Action | Purpose |
|---|---|---|---|
| GET | `/admin/login` | `Admin\AuthController@showLogin` | Display admin login form |
| POST | `/admin/login` | `Admin\AuthController@login` | Process login credentials |
| POST | `/admin/logout` | `Admin\AuthController@logout` | Log out admin |
| GET | `/admin` | `Admin\DashboardController@index` | Admin dashboard |
| GET | `/admin/orders` | `Admin\OrderController@index` | List orders |
| GET | `/admin/orders/{order}` | `Admin\OrderController@show` | View order details |
| PATCH | `/admin/orders/{order}/status` | `Admin\OrderController@updateStatus` | Update order status |
| GET | `/admin/reservations` | `Admin\ReservationController@index` | List reservations |
| GET | `/admin/reservations/{reservation}` | `Admin\ReservationController@show` | View reservation details |
| PATCH | `/admin/reservations/{reservation}/status` | `Admin\ReservationController@updateStatus` | Update reservation status (confirm / complete / cancel) |
| GET | `/admin/tables` | `Admin\TableController@index` | List tables |
| GET | `/admin/tables/create` | `Admin\TableController@create` | New table form |
| POST | `/admin/tables` | `Admin\TableController@store` | Create table |
| GET | `/admin/tables/{table}/edit` | `Admin\TableController@edit` | Edit table form |
| PATCH | `/admin/tables/{table}` | `Admin\TableController@update` | Update table (capacity, status) |
| GET | `/admin/menu/categories` | `Admin\MenuCategoryController@index` | List categories |
| GET | `/admin/menu/categories/create` | `Admin\MenuCategoryController@create` | New category form |
| POST | `/admin/menu/categories` | `Admin\MenuCategoryController@store` | Create category |
| GET | `/admin/menu/categories/{category}/edit` | `Admin\MenuCategoryController@edit` | Edit category form |
| PATCH | `/admin/menu/categories/{category}` | `Admin\MenuCategoryController@update` | Update category |
| DELETE | `/admin/menu/categories/{category}` | `Admin\MenuCategoryController@destroy` | Delete category (blocked if items reference it) |
| GET | `/admin/menu/items` | `Admin\MenuItemController@index` | List menu items |
| GET | `/admin/menu/items/create` | `Admin\MenuItemController@create` | New menu item form |
| POST | `/admin/menu/items` | `Admin\MenuItemController@store` | Create menu item |
| GET | `/admin/menu/items/{item}/edit` | `Admin\MenuItemController@edit` | Edit menu item form |
| PATCH | `/admin/menu/items/{item}` | `Admin\MenuItemController@update` | Update menu item |
| PATCH | `/admin/menu/items/{item}/availability` | `Admin\MenuItemController@toggleAvailability` | Toggle item availability |
| GET | `/admin/reports` | `Admin\ReportController@index` | Reports dashboard |
| GET | `/admin/reports/weekly` | `Admin\ReportController@weekly` | Generate weekly PDF report |
| GET | `/admin/reports/monthly` | `Admin\ReportController@monthly` | Generate monthly PDF report |

> Route note: `/admin/login` uses `guest` middleware (redirects already-authenticated admins to the dashboard). All other `/admin/*` routes use `auth` + `EnsureAdminRole`.

---

## 5. Models and Relationships

### 5.1 Model List

| Model | Table | Purpose |
|---|---|---|
| `User` | `users` | Admin accounts only — single `admin` role, no guest accounts |
| `MenuCategory` | `menu_categories` | Menu sections (e.g. Mains, Drinks) |
| `MenuItem` | `menu_items` | Individual dishes — soft-deleted to preserve historical references |
| `Table` | `tables` | Physical restaurant tables |
| `Order` | `orders` | Guest food orders (standalone, not linked to a reservation) |
| `OrderItem` | `order_items` | Line items — `item_name` and `unit_price` are purchase-time snapshots |
| `Payment` | `payments` | Payment record — belongs to either an Order or a Reservation, never both |
| `Reservation` | `reservations` | Table reservations (table_only or table_and_food) |
| `ReservationFood` | `reservation_foods` | Pre-ordered food items — `item_name` and `unit_price` are purchase-time snapshots |

### 5.2 Relationships

```text
User
└── (no guest ownership relationships — admin management only)

MenuCategory
└── hasMany → MenuItem

MenuItem (soft-deleted)
├── belongsTo → MenuCategory
├── hasMany → OrderItem       (nullable FK — preserved after soft delete)
└── hasMany → ReservationFood (nullable FK — preserved after soft delete)

Table
└── hasMany → Reservation

Order
├── hasMany → OrderItem
├── hasOne  → Payment         (nullable — only for paid orders)

OrderItem
├── belongsTo → Order
└── belongsTo → MenuItem      (nullable — item may be soft-deleted)

Payment
├── belongsTo → Order         (nullable — set for standalone food orders)
└── belongsTo → Reservation   (nullable — set for table_and_food reservations)
   ↑ Exactly one of order_id / reservation_id is set — never both, never neither.

Reservation
├── belongsTo → Table
├── hasMany   → ReservationFood
├── hasOne    → Payment       (nullable — only for table_and_food)

ReservationFood
├── belongsTo → Reservation
└── belongsTo → MenuItem      (nullable — item may be soft-deleted)

   ↑ Exactly one of order_id / reservation_id is set.
   ↑ The referenced record must have status = completed.
```

### 5.3 Key Field Rules

**Snapshots:** `order_items.item_name`, `order_items.unit_price`, `reservation_foods.item_name`, `reservation_foods.unit_price` are written at transaction time and never updated. Menu renames or price changes do not alter historical records.

**Soft deletes:** `menu_items` uses `deleted_at`. This preserves FK references from `order_items` and `reservation_foods` after an item is removed.

**Single total:** `orders.total` is the final payable amount. No `subtotal`, discount, or tax fields — price adjustments are out of V1 scope.

**`order_number` / `reservation_number`:** customer-facing references (e.g. `ORD-2026-00125`, `RES-2026-00125`). Generated server-side at creation time. Displayed on confirmations, receipts, and emails.

---

## 6. Authentication and Authorization

### 6.1 Admin Authentication

| Concern | Implementation |
|---|---|
| Mechanism | Laravel session-based authentication (built-in) |
| Table | `users` — admin accounts only |
| Role column | `users.role` — value is always `admin`; no other values |
| Password storage | Laravel default hasher (bcrypt) |
| Account provisioning | `AdminSeeder` — seeded directly, no self-registration route |
| Login route | `POST /admin/login` via `Admin\AuthController@login` |
| Login page | `GET /admin/login` — uses `guest` middleware (already-logged-in admins redirect to dashboard) |
| Session guard | Laravel default web guard |

### 6.2 Admin Authorization

| Concern | Implementation |
|---|---|
| Middleware | `EnsureAdminRole` — checks `auth()->user()->role === 'admin'` |
| Applied to | All `/admin/*` routes except `/admin/login` |
| Unauthenticated request | Redirect to `/admin/login` |
| Authenticated non-admin | 403 response |
| No Policies | Admin access is a single role check — Policy classes are not used unless per-record ownership logic genuinely emerges |

### 6.3 Guest Access (Signed URLs)

Guests never log in. Protected guest actions use `URL::temporarySignedRoute()`:

| Action | Signed route |
|---|---|
| Download receipt | `GET /orders/{order}/receipt` |
| Cancel reservation | `GET /reservation/{reservation}/cancel` + `POST /reservation/{reservation}/cancel` |

Signed links are included in the relevant confirmation email. The `signed` middleware validates the signature before the controller runs. Invalid, tampered, or expired signatures return a generic "this link is no longer valid" message — no hint about which part failed, no fallback to raw ID access.

---

## 7. Validation

Laravel Form Requests enforce server-side validation before any controller logic executes. Client-side validation may improve UX but is never the security boundary.

| Form Request | Used by |
|---|---|
| `StoreOrderRequest` | `CheckoutController@store` |
| `StoreReservationRequest` | `ReservationController@store` |
| `StoreContactRequest` | `ContactController@store` |
| `UpdateMenuItemRequest` | `Admin\MenuItemController@update` |
| `UpdateTableRequest` | `Admin\TableController@update` |

Key validation rules:

- Required fields (name, email, phone, date, time, guest count, quantities)
- Email format
- Phone format
- Date must not be in the past
- `guest_count` must not exceed the selected table's `capacity`
- `menu_item_id` must reference an `is_available = true` item
- `quantity` must be a positive integer
- Order `total` is calculated server-side from database prices — never trusted from client input
- Admin input validated before any database write

---

## 8. Order Processing

```text
Guest selects menu items
      ↓
Session-based cart (`cart.order`; Table + Food uses isolated `cart.reservation`)
      ↓
GET /checkout — CheckoutController@create
      ↓
Guest enters name, email, phone, order_type
      ↓
POST /checkout — CheckoutController@store
      ↓
StoreOrderRequest validates input
      ↓
OrderService:
  - Verify all cart items are still is_available = true
  - Calculate total from live database prices (never from client)
  - Create Order (status: pending)
  - Create OrderItem rows (snapshot item_name + unit_price)
  - Keep the relevant cart while payment is pending
      ↓
Redirect to FIUU
      ↓
FIUU processes payment
      ↓
POST /payment/callback — PaymentController@callback
      ↓
FiuuPaymentService:
  - Verify callback signature
  - Match payment reference to Order
  - Verify amount matches Order.total
  - Upsert Payment row (keyed on provider_reference — idempotent)
      ↓
Payment verified?
  ├── Yes → Order.status = confirmed
  │         ReceiptService generates PDF (on demand)
  │         OrderConfirmationMail sent (with signed receipt link + PDF attached)
  │         Redirect to /orders/{order}/confirmation
  │
  └── No  → Payment.status = failed
             Order remains pending
             Guest can retry the existing eligible pending transaction
```

**Total calculation rule:** `Order.total` is computed from `menu_items.price` × `order_items.quantity` at checkout time. It is stored on the order and never recalculated from changed menu prices later.

The cart and checkout resolve current menu items and prices from the database. Unavailable or deleted items remain visible for removal, are excluded from the displayed total, and block checkout until removed. The applicable cart context is cleared only after verified payment confirms its parent.

---

## 9. Reservation Processing

### 9.1 Table Only

```text
POST /reservation — ReservationController@store
      ↓
StoreReservationRequest validates input
      ↓
ReservationService:
  - DB transaction with lockForUpdate()
  - Query reservations on requested table_id for overlapping window
    (reservation_time to reservation_time + 120 minutes)
  - Exclude stale pending rows older than 15 minutes
      ↓
Table available?
  ├── No  → Return availability conflict response
  │         Guest prompted to select another date / time
  │
  └── Yes → Create Reservation (reservation_type: table_only, status: confirmed)
             Reservation number available for confirmation and lookup
             ReservationConfirmationMail sent (reservation number and signed cancellation link included)
             Redirect to /reservation/{reservation}/confirmation
```

### 9.2 Table + Food

```text
POST /reservation — ReservationController@store
      ↓
StoreReservationRequest validates input + food items
      ↓
ReservationService:
  - DB transaction with lockForUpdate()
  - Availability check (same overlap logic as table_only)
      ↓
Table available?
  ├── No  → Return availability conflict response
  │
  └── Yes → Create Reservation (reservation_type: table_and_food, status: pending)
             Create ReservationFood rows (snapshot item_name + unit_price)
             Calculate food total from live database prices
             Redirect to FIUU
                   ↓
             POST /payment/callback — PaymentController@callback
                   ↓
             FiuuPaymentService verifies callback
                   ↓
             Payment verified?
               ├── Yes → Reservation.status = confirmed
               │         Payment row upserted (reservation_id set, order_id null)
               │         ReservationConfirmationMail sent with reservation number
               │         ReceiptService generates food pre-order PDF (attached to mail)
               │
               └── No  → Payment.status = failed
                          Reservation remains pending
                          After 15 minutes: pending row excluded from availability queries
                          Guest shown payment failure / expiry state
```

### 9.3 15-Minute Payment Hold

A `table_and_food` reservation in `pending` status blocks its table slot immediately. If FIUU payment is not verified within 15 minutes:

- The availability query filters out `pending` reservations where `created_at < now() - 15 minutes`
- The slot is freed without a scheduled job or cron
- The stale `pending` row may be cleaned up by a separate artisan command for admin list hygiene — this is optional and has no effect on correctness

---

## 10. Payment Integration (FIUU)

`FiuuPaymentService` remains the real gateway adapter. Development and demonstration environments use `MockPaymentService`, which redirects an eligible pending payment to a signed mock gateway. The browser submits only a fake payment method and demo outcome; the gateway reloads the payment, derives reference/amount/currency server-side, creates a mock-only HMAC result, and sends it through the existing verification and idempotent application path. Success, failure, abandonment, retry, callback processing, and browser return remain separate concepts. The mock processes no real funds and does not prove real FIUU compatibility.

```text
Laravel
  ↓
FiuuPaymentService::prepare()
  - Build payment request (amount, reference, return URL, callback URL)
  - Redirect guest to FIUU hosted payment page
  ↓
FIUU processes payment
  ↓
Two responses:
  1. Browser redirect → GET /payment/return
     PaymentController@return
     Display "verifying payment" state only — not proof of payment
  
  2. Server-to-server callback → POST /payment/callback
     PaymentController@callback
     FiuuPaymentService::verify()
       - Validate FIUU signature
       - Match reference to Order or Reservation
       - Verify amount
       - Upsert Payment row on provider_reference (idempotent — duplicate callbacks safe)
       - Update Order.status or Reservation.status
       - Trigger email
```

**Payment rules:**
- Browser redirect is never treated as proof of payment
- `payments.provider_reference` has a unique constraint — duplicate callbacks update the existing row
- `payments.status` values: `pending`, `paid`, `failed`, `refunded`
- Payment status is written only by the verified FIUU callback — admin views it read-only
- A payment row belongs to exactly one Order or one Reservation — `order_id` and `reservation_id` are mutually exclusive, one must be set

---

## 11. Rasa AI Integration

```text
POST /ai/recommendations — AiRecommendationController@recommend (rate-limited)
      ↓
Validate bounded guest text and/or canonical allergen exclusions; reject customer-posted structured preference selector fields
Interpret supported text into internal menu-attribute, category, allergen-exclusion, and per-dish price filters
      ↓
AiRecommendationController filters database candidates by availability, active category and interpreted constraints
AiRecommender::recommend(items, preferences)
  - MockAiRecommender uses deterministic matching by default
  - Configured OpenAiService sends eligible candidate attributes and preferences only
      ↓
OpenAI returns recommendation response
      ↓
AiRecommendationController validates returned IDs:
  - Reapply database price, availability, active-category and preference constraints
  - Drop unknown or ineligible IDs and cap results at three
      ↓
Return validated recommendations to view
```

The mock and OpenAI recommenders receive only eligible candidates. The server rechecks returned IDs against the same database-backed filters. Unsupported, contradictory, and allergy-related text must not bypass this boundary or imply allergen safety.

**Rules:**
- OpenAI API key lives in `.env` — never in Blade templates or client-side JavaScript
- AI may only recommend items that exist in `menu_items` and have `is_available = true`
- AI failure returns a graceful fallback — Rasa AI is never a blocking dependency for ordering
- Only the dedicated Rasa AI page uses AiRecommender; ContactController does not call a recommendation provider

---

## 12. Reservation Identifier

```text
Reservation created
      ↓
Unique readable reservation_number assigned (e.g. RES-2026-00125)
      ↓
Displayed in the ReservationConfirmationMail, confirmation page, and admin records
```

The reservation number supports administrator lookup/check-in validation only. Signed URLs are separate credentials for cancellation and receipt retrieval; the reservation record in the database remains authoritative.

---

## 13. Email (Laravel Mail)

Five confirmed Mailables — no others:

| Mailable | Trigger | Contains |
|---|---|---|
| `OrderConfirmationMail` | Payment verified (Order.status → confirmed) | Order summary, signed receipt link, PDF receipt attached |
| `ReservationConfirmationMail` | Reservation.status → confirmed | Reservation details, readable reservation number, signed cancellation link |
| `ReservationCancellationMail` | Reservation.status → cancelled | Cancellation confirmation |
| `GoogleReviewInvitationMail` | Order or Reservation.status → completed | Optional link to the configured external Google Review destination |
| `ContactEnquiryMail` | Contact form submitted | Guest name, email, message — sent to restaurant |

**No `PaymentConfirmationMail`** — the receipt PDF is attached to `OrderConfirmationMail` after payment is verified. No separate payment confirmation email exists.

Email is sent synchronously (no queue) for V1. A failed email is logged — it does not roll back the underlying order, payment, or reservation transaction. SMTP credentials live in `.env`.

---

## 14. Receipt Generation (DomPDF)

Library: `barryvdh/laravel-dompdf`

```text
Payment verified
      ↓
ReceiptService::generate(Order $order)
  - Retrieve Order, OrderItems, Payment from database
  - Render Blade view as PDF
  - Return PDF for attachment or download
```

**No `receipts` table.** Receipts are generated on demand each time — nothing is persisted to disk. Content is always sourced from the database snapshot data (`order_items.item_name`, `order_items.unit_price`) so historical accuracy is preserved regardless of menu changes.

Receipt is accessible via the signed URL at `GET /orders/{order}/receipt`. It is also attached to `OrderConfirmationMail` at the time of sending.

---

## 15. Contact Enquiry Processing

```text
POST /contact — ContactController@store
      ↓
StoreContactRequest validates: name, email, message (all required)
      ↓
ContactEnquiryMail dispatched to restaurant email address
      ↓
Success response shown to guest
```

No database record is created. If mail fails, the guest receives an error response — not a false success. The guest's form data is preserved so they do not need to re-enter the message.

---

## 16. External Google Review Invitation

After an order or reservation first reaches `completed`, the relevant admin status transition sends `GoogleReviewInvitationMail` with the configured external Google Review destination. The email makes clear that a review is optional.

Le Miting has no feedback model, table, form, route, signed review link, internal review-management screen, or review report. The application does not call a Google API, submit a review, retrieve a review, or include internal transaction identifiers in the external URL. Google exclusively receives and stores a review submitted by the customer.

---

## 17. Error Handling

| Scenario | Backend behaviour |
|---|---|
| Validation failure | Return validation errors to the form — no redirect, no data loss |
| Rasa AI failure | Log the error, return graceful fallback message, guest continues to normal menu |
| No table available | Return conflict response, guest prompted to select another slot |
| Reservation conflict (race condition) | DB transaction + row lock catches it — one request succeeds, the other receives a conflict response |
| Payment failure | Log, set `payments.status = failed`, order/reservation remains pending, guest shown retry option |
| Payment hold expired | Stale pending reservation excluded from availability queries — slot freed automatically |
| Signed URL invalid / expired | `signed` middleware intercepts — generic "link no longer valid" response |
| Unauthenticated admin request | `auth` middleware intercepts — redirect to `/admin/login` |
| Authenticated non-admin request | `EnsureAdminRole` intercepts — 403 response |
| Email send failure | Log failure, return appropriate application response — does not roll back the transaction |
| Unavailable menu item at checkout | `OrderService` flags the item — checkout blocked until guest removes it |

---

## 18. Backend Security

| Concern | Implementation |
|---|---|
| Admin authentication | Laravel session-based auth — `auth` middleware on all `/admin/*` routes |
| Admin authorization | `EnsureAdminRole` middleware — checks `users.role === 'admin'` |
| Password hashing | Laravel default hasher (bcrypt) |
| CSRF protection | Laravel CSRF middleware on all state-changing web requests |
| Input validation | Form Requests — validated before any processing or database write |
| SQL injection | Eloquent ORM and query builder — no raw SQL from user input |
| Mass assignment | Explicit `$fillable` on every model — no `$guarded = []` |
| Guest resource protection | `URL::temporarySignedRoute()` — signature verified before controller runs |
| FIUU callback trust | Server-to-server signature verification — browser redirect not trusted |
| Rasa AI output | Validated server-side against `menu_items` before rendering — AI cannot invent dishes |
| Credentials | `.env` only — never in source code, never committed to Git |
| HTTPS | Required in production (Exabytes) |
| Rate limiting | `throttle` middleware on `/ai/recommendations` and `/admin/login` |
| Payment status writes | Written only by verified FIUU callback — admin panel is read-only for payment status |

---

## 19. Admin Management

CRUD operations for all managed entities, all protected by `auth` + `EnsureAdminRole`.

**Orders** — view list, view details, update `status` (pending → confirmed → preparing → ready → completed / cancelled). Payment status is read-only — not editable by admin.

**Reservations** — view list, search by `reservation_number`, confirm, cancel, mark completed, view guest count, view table, adjust table assignment, view pre-ordered food. Cancelled or completed reservations must not be presented as valid active check-ins.

**Tables** — create, edit, set `capacity`, toggle `status` between `available` and `maintenance`. Placing a table into maintenance is blocked when future confirmed reservations depend on it unless those reservations are first reassigned or cancelled.

**Menu Categories** — create, edit, delete. Deletion blocked if any `menu_items` reference the category — error explains the reason.

**Menu Items** — create, edit, toggle `is_available`, update price, description, image, category, `spice_level`, `protein_type`, `dietary_type`, `flavor_profile`, `is_featured`. Soft-delete to preserve historical records.

**Reports** — generate weekly and monthly PDFs on demand for sales and reservations. Report generation uses server-side aggregation from the database. No daily reports — not in PRD scope.

---

## 20. Final Backend Architecture

```text
                         LE MITING
                             │
                     Laravel Application
                             │
       ┌─────────────────────┼──────────────────────┐
       │                     │                      │
   Controllers            Services              Middleware
       │                     │                      │
       │         ┌───────────┼───────────┐          │
       │         │           │           │          │
       │   FiuuPayment    OpenAI      Receipt       │
       │   Service        Service     Service       │
       │         │           │           │          │
       └─────────┴───────────┴───────────┴──────────┘
                             │
                         Eloquent ORM
                             │
                           MySQL
                             │
          ┌──────────────────┼──────────────────────┐
          │                  │                       │
       Orders          Reservations              Menus
       Payments          Tables
          │                  │                       │
          └──────────────────┼───────────────────────┘
                             │
                    External Integrations
                             │
              ┌──────────────┼──────────────┐
              │              │              │
            FIUU          OpenAI        Email/SMTP
         (payment)      (Rasa AI)    (Laravel Mail)
              │              │              │
              └──────────────┼──────────────┘
                             │
                      Guest / Admin
```

---

## REC-02B — Allergen assessment administration

`MenuAllergenAssessmentController` provides authenticated ensure-admin GET index/edit and PATCH save/invalidate routes under `/admin/menu/items/{menuItem}/allergens`. Form Requests additionally require role=admin. Save accepts controlled recipe/cross-contact statuses, bounded evidence, documented-information attestation and a stale-form context; reviewer identity and UTC review time are server-owned.

`MenuAllergenAssessmentService` saves transactionally with a menu-item row lock and unique pair enforcement. Admin menu updates use the same row lock and invalidate assessments when name, description, spice, protein, dietary or flavour attributes change. Price, image, availability and ordering edits do not invalidate reviews. Supplier, ingredient and preparation changes not represented in menu fields require explicit admin invalidation with a reason; this conservatively invalidates every assessment for the item. Prior evidence remains visible until replaced by a new review.

`MenuItemAllergenAssessment::isCurrentFor()` requires provenance, no invalidation and a matching source fingerprint. Missing, incomplete or stale records must be treated as unknown. REC-02B itself introduces no elapsed-time expiry or customer filtering; REC-02C below defines the approved, assessment-backed exclusion path.

## REC-02C — Fail-closed customer allergen exclusions

`POST /ai/recommendations` accepts optional `preferences_text` (string, maximum 300 characters) and optional `excluded_allergens[]`. Require at least one non-blank text value or a non-empty array of valid canonical codes: `peanut`, `tree_nuts`, `milk`, `egg`, `soy`, `wheat_gluten`, `fish`, `crustacean_shellfish`, `molluscs`, `sesame`. Reject legacy structured preference selector inputs (`category`, `spice_level`, `protein`, `dietary`, `flavor`). `AiRecommendationOptions::interpretText()` maps only explicit supported English/Malay allergy/exclusion terms to those codes; merge and deduplicate text-derived codes with checkbox values. Generic/unspecified allergy or intolerance and unmapped allergens/intolerances fail closed with the staff-confirmation notice and zero calls to either provider. Use the phrase mappings listed in the PRD; aliases are recognition terms, not ingredient evidence.

Before calling a provider, constrain candidates to available menu items in active categories. For each excluded code, a present assessment row is authoritative: only a current row with `recipe_status = reviewed_absent`, `cross_contact_status = reviewed_no_known_risk`, and complete reviewer/time/evidence provenance qualifies; stale, invalidated, incomplete, unknown, contains, or possible rows exclude the item. When no row exists for that item/code, apply deterministic canonical English/Malay ingredient matching to its name and description, ignoring explicit negative phrases; positive matches exclude and non-matches may proceed with a menu-check badge. Apply this same rule before provider calls and to every returned ID. If no candidates remain, return no recommendations and a staff-confirmation notice without invoking a provider; provider output is never authoritative for eligibility or allergen facts.

For eligible returned results, render the shared-kitchen caution, instruct customers to tell staff before ordering, and show `Reviewed:` provenance for a qualifying current assessment or `Menu check: [allergen] not listed in dish name or description · Confirm with staff` when no row exists. Menu checks are not ingredient or cross-contact verification. Prohibit allergy-safe, allergen-free, 100%-safe, and equivalent claims in UI and OpenAI prompts. Relevant menu fingerprint/revision changes and explicit invalidation disqualify an existing assessment pending a new review. Provider output remains non-authoritative.
