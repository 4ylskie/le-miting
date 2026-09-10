# Product Requirements Document (PRD)

# Le Miting
## Malaysian Restaurant Ordering & Reservation System
### Final Year Project — Laravel

**Version:** 3.1  
**Status:** Product Requirements Specification  
**Project Type:** Diploma / Final Year Project

## Revision Notes (v3.0 → v3.1)

This revision closes gaps identified in review. No previously-defined requirement was removed; the following were added or clarified:

- Reservation hold expiry rule for unpaid Table + Food bookings (§15, BR-04).
- Guest record access protection — signed links for receipts, cancellation, and feedback (§45).
- Order type (dine-in / takeaway) added to checkout, confirmation, and admin order view.
- Admin reservation lookup/validation by reservation number or QR content (§28).
- Cart session key separation between ordering and reservation pre-order flows (§8, FR-04).
- Category deletion rule tied to referencing menu items (§31).
- Table maintenance guard against existing future reservations (§29).
- Defined metrics for "today's revenue," report periods, and "popular menu items" (§26, §33).
- Removed "Appetite where applicable" from Rasa AI preferences (§21) — no corresponding menu attribute existed in FR-03, violating this document's own vocabulary-consistency rule.
- Added minimum field definitions for `orders`, `order_items`, `reservations`, `reservation_foods` (§34), matching the level of detail already given for menus/payments/feedback.

---

# 1. PROJECT OVERVIEW

## 1.1 Project Name

**Le Miting**

## 1.2 Product Type

Web-based Malaysian restaurant ordering and reservation system.

## 1.3 Project Purpose

Le Miting is a digital restaurant platform that allows customers to browse the restaurant menu, receive Rasa AI food recommendations, place food orders, reserve tables, pre-order food with a reservation, make online payments where applicable, receive digital receipts and confirmations, and provide feedback after dining.

The system also provides an administrative area for restaurant staff to manage:

- Orders
- Reservations
- Tables
- Menu items
- Categories
- Feedback
- Reports

Customers do not need to create an account.

The system is intended to remain simple enough for a Final Year Project while demonstrating proper Laravel architecture, database design, API integration, payment integration, document generation, email services, validation, security, and responsive web development.

**Customer problem solved:** Customers can browse Le Miting's menu, receive personalised Rasa AI recommendations, and make a reservation or food order without needing to call the restaurant, wait on hold, or visit in person — getting instant confirmation and a digital record of their booking or order.

---

# 2. PROJECT OBJECTIVES

## 2.1 Business Objectives

The system aims to:

- Reduce manual restaurant reservation handling.
- Reduce ordering and reservation errors.
- Give customers easier access to restaurant menu information.
- Provide Rasa AI menu discovery.
- Provide digital reservation confirmation.
- Provide digital order records and receipts.
- Give restaurant staff a central management system.
- Provide basic sales, reservation, and feedback reporting.

## 2.2 Academic Objectives

The system should demonstrate:

- Laravel MVC architecture.
- Laravel routing and middleware.
- Blade view development.
- Tailwind CSS integration.
- MySQL database design.
- Eloquent relationships.
- CRUD operations.
- Form validation.
- Authentication and authorization.
- OpenAI API integration.
- FIUU payment integration.
- Laravel DomPDF.
- Laravel Mail.
- Git and GitHub version control.
- Responsive web development.
- Accessibility and security practices.

Every major component must remain understandable and explainable by the student.

---

# 3. TECHNOLOGY STACK

| Category | Technology |
|---|---|
| Framework | Laravel |
| Backend | PHP |
| Frontend | Blade |
| Styling | Tailwind CSS |
| Database | MySQL |
| AI | OpenAI API |
| Payment | FIUU |
| PDF Generation | Laravel DomPDF |
| Email | Laravel Mail |
| Version Control | Git + GitHub |
| IDE | VS Code |

The stack is fixed unless a dependency is genuinely required for an existing integration.

Do not replace Laravel/Blade with React, Vue, Next.js, Django, FastAPI, or another primary framework.

---

# 4. USERS AND ROLES

## 4.1 Customer

Customers are guest users.

Customers do not need:

- Registration
- Login
- Customer account
- Password

Customers identify themselves using information such as:

- Name
- Email
- Phone number

Customers can:

- Browse menu
- Use Rasa AI
- Add items to cart
- Place orders
- Reserve tables
- Pre-order food
- Make applicable payments
- Receive confirmations
- Cancel eligible reservations
- Submit feedback after completion

## 4.2 Admin

The system has one administrative role.

Administrators must log in.

Admin users can:

- View dashboard
- Manage orders
- Manage reservations
- Manage tables
- Manage menus
- Manage categories
- View feedback
- Generate reports

Multi-admin role hierarchies are out of scope.

---

# 5. SYSTEM SCOPE

## 5.1 In Scope

### Customer

- Landing page
- Menu browsing
- Menu search
- Menu filtering
- Rasa AI recommendation
- Session-based cart
- Guest checkout
- FIUU payment
- Order confirmation
- PDF receipt
- Email confirmation
- Table reservation
- Table + food reservation
- Table availability checking
- Reservation cancellation
- Reservation QR code
- Post-dining feedback
- Google Review redirect after feedback
- Contact Us page with Rasa AI recommendation

### Admin

- Admin authentication
- Dashboard
- Order management
- Reservation management
- Table management
- Menu management
- Category management
- Feedback management
- Weekly PDF reports
- Monthly PDF reports

### Cross-Cutting

- Responsive design
- Basic accessibility
- Server-side validation
- CSRF protection
- Authentication
- Authorization
- Secure API credentials
- Server-side price and total calculation
- Graceful failure handling
- Data integrity

**V1 irreducible core:** Le Miting ships when a guest can browse the menu, place a food order with FIUU payment, and reserve a table — and an admin can manage all of it from a central panel. Every other feature in §5.1 is additive to this core.

## 5.2 Out of Scope

The following are not part of the project MVP:

- Customer accounts
- Customer login
- Customer order history tied to an account
- Loyalty program
- Delivery management
- Rider dispatch
- Multi-restaurant management
- Multi-branch management
- Multi-admin role system
- Native mobile application
- Advanced business intelligence
- Complex analytics
- Real-time customer chat

---

# 6. SYSTEM ARCHITECTURE

The project uses a single Laravel application.

```text
Customer
    ↓
Blade + Tailwind
    ↓
Laravel Routes
    ↓
Controllers
    ↓
Services / Models
    ↓
MySQL


External Integrations

Laravel
 ├── OpenAI API
 ├── FIUU
 ├── Laravel Mail
 └── Laravel DomPDF


Admin

Admin Login
    ↓
Middleware
    ↓
Admin Blade
    ↓
Admin Controllers
    ↓
MySQL
```

Do not create separate frontend and backend applications.

Do not implement microservices.

---

# 7. CUSTOMER FLOW

## 7.1 Ordering Flow

```text
Landing Page
    ↓
Order
    ↓
Browse Menu / Rasa AI
    ↓
Cart
    ↓
Checkout
    ↓
FIUU Payment
    ↓
Payment Verification
    ↓
Order Confirmation
    ↓
PDF Receipt + Email
    ↓
Dining / Order Completion
    ↓
Feedback Invitation Email
    ↓
Feedback
```

## 7.2 Reservation Flow

### Table Only

```text
Landing
    ↓
Reservation
    ↓
Select Date
    ↓
Select Time
    ↓
Select Guest Count
    ↓
Check Available Tables
    ↓
Select Table
    ↓
Customer Details
    ↓
Confirm Reservation
    ↓
QR Code
    ↓
Reservation Email
    ↓
Restaurant Visit
    ↓
Admin Marks Completed
    ↓
Feedback Invitation Email
    ↓
Feedback
```

### Table + Food

```text
Landing
    ↓
Reservation
    ↓
Table + Food
    ↓
Select Date
    ↓
Select Time
    ↓
Guest Count
    ↓
Check Available Tables
    ↓
Select Table
    ↓
Pre-order Food
    ↓
Cart
    ↓
Checkout
    ↓
FIUU Payment
    ↓
Payment Verification
    ↓
Reservation Confirmation
    ↓
QR Code
    ↓
Email
    ↓
Restaurant Visit
    ↓
Admin Marks Completed
    ↓
Feedback Invitation Email
    ↓
Feedback
```

---

# 8. FUNCTIONAL REQUIREMENTS

## FR-01 Landing Page

The landing page shall:

- Display Le Miting branding.
- Provide access to ordering.
- Provide access to reservations.
- Provide access to Rasa AI.
- Introduce the restaurant.
- Display featured menu items.
- Provide navigation to major customer functions.
- Provide access to Contact Us.

Visual styling is defined separately in `UI_UX_BRIEF.md`.

---

## FR-02 Menu Browsing

Customers shall be able to:

- View menu items.
- View menu categories.
- Search by dish name.
- Filter by category.
- Filter by relevant menu attributes.
- View price.
- View description.
- View availability.
- View image.
- View rating where available.

---

## FR-03 Menu Data Attributes

Menu items shall support recommendation and filtering attributes.

Minimum menu fields should include:

```text
id
menu_category_id
name
description
price
image
spice_level
protein_type
dietary_type
flavor_profile
is_available
is_featured
timestamps
```

The attribute vocabulary must be consistent between:

- Menu filtering
- Rasa AI preferences
- AI recommendation logic

Avoid creating separate, conflicting vocabularies for the same concept.

---

## FR-04 Shopping Cart

The cart shall be session-based.

Customers shall be able to:

- Add items.
- Increase quantity.
- Decrease quantity.
- Remove items.
- Clear cart.
- View total.

Prices and totals must be recalculated server-side, sourced from the current `menu_items` record at calculation time — not from a price cached in the session when the item was added.

Client-submitted prices or totals must never be trusted as final values.

An empty cart cannot proceed to checkout.

The ordering cart and the Table + Food pre-order cart use separate session keys (e.g. `cart.order` and `cart.reservation`). A customer browsing the menu while also building a reservation pre-order must not have one flow overwrite or merge into the other.

If a cart item's `is_available` becomes `false` between add-to-cart and checkout, checkout must flag it and require removal before proceeding — never silently charge for or silently drop an unavailable item.

---

## FR-05 Checkout

Checkout shall collect:

- Customer name
- Email
- Phone number
- Order type: `dine-in` or `takeaway`
- Special request or notes where applicable

Order type must be validated against the fixed enum above — it determines how staff prepare and hand off the order and is required, not optional.

The system shall:

- Validate submitted information.
- Display order items.
- Display quantities.
- Display item prices.
- Display total.
- Display customer information.
- Display payment information.

---

# 9. PAYMENT REQUIREMENTS

## FR-06 FIUU Payment

FIUU shall be used for applicable payments.

Payment flow:

```text
Create Pending Transaction
        ↓
Send Payment Request
        ↓
Customer Pays
        ↓
FIUU Callback / Return
        ↓
Verify Payment
        ↓
Update Payment Status
```

The system must never mark payment as successful merely because the customer returned to the website.

Payment status may be:

```text
pending
paid
failed
refunded
```

FIUU credentials must remain in `.env`.

If real FIUU credentials are unavailable during development, use a clearly labelled sandbox/mock flow.

Mock payment success must never be presented as real payment.

---

# 10. ORDER REQUIREMENTS

## FR-07 Order Confirmation

After successful order processing, show:

- Order number
- Customer name
- Order type
- Order items
- Quantity
- Total
- Payment status
- Order status
- Order date/time

Order status:

```text
pending
confirmed
preparing
ready
completed
cancelled
```

Payment status remains separate from order status.

Example:

```text
Order Status: preparing
Payment Status: paid
```

---

# 11. PDF RECEIPT

## FR-08 Receipt Generation

Use Laravel DomPDF.

Receipt shall include:

- Le Miting name
- Order number
- Customer name
- Customer contact
- Order date
- Order items
- Quantity
- Individual prices
- Total
- Payment status

Receipt must be a proper restaurant receipt/invoice rather than raw HTML dumped into a PDF.

Suggested route:

```text
/orders/{order}/receipt
```

Since there is no customer login, this route must not be reachable by ID alone — see Guest Record Access Protection in §45. The link sent by email is the intended access path.

---

# 12. EMAIL REQUIREMENTS

## FR-09 Email Notifications

Use Laravel Mail / Mailables.

Send:

### Order Confirmation

Contains:

- Order number
- Order summary
- Total
- Payment status

### Reservation Confirmation

Contains:

- Reservation number
- Date
- Time
- Guest count
- Table number if assigned
- QR code
- Reservation status

### Cancellation Notification

Contains:

- Reservation number
- Cancellation status
- Relevant payment/refund information if applicable

### Feedback Invitation

After an order or reservation has been marked `completed`, send a feedback invitation email containing a link to the feedback form.

Feedback invitations must only be sent for completed transactions.

SMTP credentials must remain in `.env`.

---

# 13. RESERVATION REQUIREMENTS

## FR-10 Reservation Types

Customers may choose:

```text
Reserve Table Only
```

or:

```text
Reserve Table + Food
```

---

## FR-11 Reservation Information

Collect:

- Name
- Email
- Phone
- Date
- Time
- Guest count
- Reservation type
- Special request

---

## FR-12 Table Availability

Table availability must be calculated using:

- Selected date
- Selected time
- Reservation duration
- Existing active reservations
- Table capacity
- Maintenance status

Do not use a single static `occupied` value to determine availability for all times.

---

# 14. RESERVATION TIME RULE

## BR-02 Reservation Duration

Each reservation occupies a table for:

**2 hours**

Example:

```text
7:00 PM → 9:00 PM
```

A table booked from 7:00 PM to 9:00 PM should still be available for:

```text
3:00 PM
```

and, assuming no other booking:

```text
9:30 PM
```

Availability must be determined from overlapping time windows.

---

# 15. DOUBLE-BOOKING PREVENTION

## BR-03

The system must prevent overlapping active reservations for the same table.

Conceptually:

```text
Requested Start < Existing End
AND
Requested End > Existing Start
```

If the time windows overlap, the table cannot be selected.

Do not rely solely on a boolean table status.

## BR-04 Reservation Hold Expiry

Reservation confirmation timing differs by type:

- **Table Only** (no payment step): the reservation is created with status `confirmed` immediately on submission, once the availability check passes. There is no hold period to define, because there is no payment gate that could be abandoned.
- **Table + Food** (payment required): selecting a table creates the reservation with status `pending` at that moment — this is what blocks the slot for BR-03's overlap check. If FIUU payment is not completed within **15 minutes** of creation, the hold expires.

To keep this simple and avoid requiring a scheduled job for correctness: the availability check in FR-12 excludes `pending` reservations older than the 15-minute window when computing whether a slot is free. This means the *query itself* is always correct, even if the stale `pending` row is still physically in the table. An optional cleanup command may later flip long-stale `pending` rows to `cancelled` for admin-list hygiene, but this is a housekeeping convenience, not something correctness depends on.

Once FIUU payment is verified, the reservation transitions from `pending` directly to `confirmed` (no separate manual admin confirmation step in the normal path). Admin's "Confirm reservation" action (§28) exists for manual/edge-case use, not as a required gate in the standard flow.

---

# 16. TABLE MANAGEMENT

## FR-13 Table States

Admin-maintained table state:

```text
available
maintenance
```

Reservation availability is calculated dynamically.

For customer table selection, show:

```text
available
selected
booked-for-this-slot
```

A table booked for one time slot must not appear unavailable for the entire day.

---

# 17. TABLE CAPACITY

A table can be selected only when:

```text
guest_count <= table.capacity
```

The system must prevent assigning an unsuitable table.

---

# 18. TABLE SELECTION UI REQUIREMENT

Use a simple accessible button grid for table selection.

Do not create a custom SVG floor plan.

The grid must display:

- Table number
- Capacity
- Availability

States:

```text
Available
Selected
Booked for this slot
```

Maintenance tables should not be selectable.

Table availability must be calculated for the selected date and time.

---

# 19. RESERVATION CANCELLATION

## FR-14 Table Only

Customers may cancel a table-only reservation any time before the reservation time, subject to any future restaurant policy.

No reservation payment is required for table-only reservations.

## FR-15 Table + Food

Before payment:

Customer may cancel without payment consequences.

After payment:

Customer cannot perform an automatic self-service refund.

The reservation may be cancelled, but refund handling is performed by admin according to the restaurant's refund policy.

Payment status can be updated to:

```text
refunded
```

when the refund has actually been processed.

As with the receipt route, the cancellation action must not be reachable from a guessed or incremented reservation number — see §45.

---

# 20. RESERVATION QR CODE

## FR-16

Generate a QR code after successful reservation.

The QR code must contain only a non-sensitive reservation identifier.

Example:

```text
RES-2026-00125
```

Do not store:

- Customer name
- Email
- Phone number
- Other personal information

inside the QR payload.

The QR code is used for:

- Reservation validation
- Customer check-in
- Identifying the reservation for staff

The QR code is NOT used for:

- Payment
- Feedback
- Receipt retrieval

At check-in, staff look up the reservation by its identifier (see FR-22 in §28). The lookup must reject reservations that are not currently `confirmed` — a `cancelled` or already-`completed` reservation must not be treated as a valid check-in, even if the QR code itself still scans.

---

# 21. RASA AI

## FR-17 Rasa AI

Rasa AI is an optional menu recommendation feature.

Customers may select preferences such as:

- Spice level
- Protein
- Flavor
- Dietary preference
- Beverage preference

("Appetite" was removed from this list — it had no corresponding field in FR-03's menu attributes, which breaks the vocabulary-consistency rule below. If portion size becomes a real feature, add `portion_size` to FR-03 first, then reintroduce it here.)

"Beverage preference" maps to the existing `menu_categories` filter (e.g. Drinks), not to a menu attribute field — it doesn't need a new column, unlike spice/protein/dietary/flavor which map directly to FR-03's attribute fields.

Preferences are stored in the session only, the same as the cart — never persisted to the database, consistent with the no-login design.

The same menu vocabulary should be used for:

- AI recommendation
- Menu filtering

OpenAI shall receive:

- Customer preferences
- Current available menu data
- Menu descriptions
- Relevant menu attributes
- Prices where necessary for context

The AI must recommend only items from the supplied menu data.

The AI cannot invent:

- Dish names
- Prices
- Availability
- Ratings
- Menu items

---

# 21b. CONTACT US PAGE

## FR-17b Contact Us

The Contact Us page shall:

- Display restaurant contact information:
  - Address
  - Phone number
  - Email address
  - Operating hours

- Display a contact form for customer enquiries:
  - Name (required)
  - Email (required)
  - Message (required)

- Display the Rasa AI recommendation widget.

The Rasa AI widget on this page behaves identically to FR-17 — same preference inputs, same recommendation logic, same failure handling (§22). It is a second surface for the same feature, not a separate AI integration.

The contact form is a one-way enquiry form. It does not generate an order, reservation, or any database record. Submitted messages are sent to the restaurant by email (Laravel Mail). No reply system is in scope.

---

# 22. RASA AI FAILURE HANDLING

AI must be an additive feature, not a blocking dependency.

If OpenAI fails:

- Show a clear message.
- Keep the menu available.
- Allow the customer to continue ordering manually.
- Provide a retry option where appropriate.

Ordering must continue functioning without AI.

---

# 23. FEEDBACK

## FR-18 Feedback Requirements

Customers may provide:

```text
Food Rating: 1–5
Service Rating: 1–5
Experience Rating: 1–5
Comment
```

Feedback must be linked to a specific completed:

- Order
or
- Reservation

Do not allow unlimited anonymous feedback unrelated to a completed transaction.

---

# 24. FEEDBACK TIMING

Feedback is available only after dining / completion.

The expected flow is:

```text
Order / Reservation
        ↓
Completed
        ↓
Feedback Invitation Email
        ↓
Feedback Form
        ↓
Feedback Saved
        ↓
Thank-you page with optional Google Review link
```

The reservation QR code is not used as a feedback mechanism.

After feedback is saved, the customer is shown a thank-you page that includes a direct link to Le Miting's Google review page. Clicking the link opens Google in a new tab. The system does not use the Google Places API and does not create, post, or verify reviews. The link is static — it does not carry feedback data to Google.

The feedback invitation email link is the only intended entry point to the feedback form for a given transaction — it must be a signed link bound to that specific order or reservation, not a plain form that accepts any order/reservation number typed in (see §45).

---

# 25. ADMIN AUTHENTICATION

## FR-19

Admins must authenticate before accessing:

```text
/admin/*
```

Use Laravel's standard authentication mechanisms.

Do not build password authentication from scratch.

Admin routes must be middleware protected.

Only authenticated admins may access administrative functionality.

---

# 26. ADMIN DASHBOARD

## FR-20

The dashboard shall display a simple operational snapshot.

Include:

- Today's orders
- Today's reservations
- Today's revenue
- Pending orders
- Pending reservations
- Available tables
- Recent feedback

"Today's revenue" is the sum of all `payments` with status `paid` and a payment timestamp within today, regardless of whether the payment is linked to an order or to a reservation's food pre-order — both are real revenue and should be counted together.

Avoid complex analytics beyond the project's defined scope.

---

# 27. ADMIN ORDER MANAGEMENT

## FR-21

Admin may:

- View orders
- View order details
- View customer details
- View items
- View payment status
- Update order status
- Mark orders completed
- Cancel orders where appropriate

Order statuses:

```text
pending
confirmed
preparing
ready
completed
cancelled
```

Payment statuses:

```text
pending
paid
failed
refunded
```

These must remain separate.

---

# 28. ADMIN RESERVATION MANAGEMENT

## FR-22

Admin may:

- View reservations
- Look up a reservation by reservation number or scanned/typed QR content, for check-in
- Confirm reservations (manual/edge-case use — see BR-04 for the normal auto-confirm path)
- Cancel reservations
- Mark reservations completed
- View guest count
- View selected table
- Adjust table assignment
- View pre-ordered food

The lookup above is a plain text-input search — it does not require a dedicated camera-scanning library; a phone camera app or any generic QR reader can decode the code into text, which staff paste or type into the search field.

Reservation status:

```text
pending
confirmed
completed
cancelled
```

---

# 29. ADMIN TABLE MANAGEMENT

## FR-23

Admin may:

- Add table
- Edit table
- Set capacity
- Set table to maintenance
- Return table to normal availability
- View current reservation assignments

Maintenance is an administrative table state.

Reservation availability remains time-based.

Setting a table to `maintenance` must be blocked while that table has any future `confirmed` or `pending` reservation, unless the admin explicitly reassigns or cancels those reservations first. Silently keeping the reservation while removing the table from service would leave a confirmed booking with nowhere to seat the guest.

---

# 30. ADMIN MENU MANAGEMENT

## FR-24

Admin may:

- Add menu item
- Edit menu item
- Deactivate menu item
- Update price
- Update description
- Upload image
- Assign category
- Set spice level
- Set protein type
- Set dietary type
- Set flavor profile
- Set featured state
- Set availability

Prefer deactivation over permanent deletion when historical orders reference a menu item.

---

# 31. ADMIN CATEGORY MANAGEMENT

## FR-25

Admin may:

- Add category
- Edit category
- Remove a category only if it has no menu items assigned to it
- View menu items under a category

If a category still has menu items, deletion is blocked. The admin must reassign or deactivate those items first — the same deactivate-over-delete pattern used for menu items (§30), applied one level up.

---

# 32. ADMIN FEEDBACK MANAGEMENT

## FR-26

Admin may:

- View feedback
- View food ratings
- View service ratings
- View experience ratings
- View comments
- View linked order/reservation
- View average ratings
- Filter feedback

Do not create social-media features such as:

- Likes
- Replies
- Public discussions

---

# 33. REPORTS

## FR-27

Generate reports on demand.

Required report types:

### Weekly Sales Report

Includes:

- Total orders
- Total revenue
- Completed orders
- Cancelled orders
- Popular menu items

### Monthly Sales Report

Includes:

- Total orders
- Total revenue
- Completed orders
- Cancelled orders
- Popular menu items

### Weekly Reservation Report

Includes:

- Total reservations
- Completed reservations
- Cancelled reservations
- Guest count
- Popular reservation times

### Monthly Reservation Report

Includes:

- Total reservations
- Completed reservations
- Cancelled reservations
- Guest count
- Popular reservation times

### Weekly Feedback Report

Includes:

- Average food rating
- Average service rating
- Average experience rating
- Review count
- Rating distribution

### Monthly Feedback Report

Includes:

- Average food rating
- Average service rating
- Average experience rating
- Review count
- Rating distribution

Use Laravel DomPDF.

Reports should be generated from current transaction data.

Do not create a separate `reports` database table unless a future requirement makes persistent report storage necessary.

Report periods are rolling windows ending today, not calendar-aligned: "weekly" means the most recent 7 days, "monthly" means the most recent 30 days. This avoids edge-case handling for partial calendar weeks/months and is simpler to test.

"Popular menu items" ranks by quantity sold within the report period, not by revenue — a cheap, frequently-ordered side dish should be able to outrank an expensive, rarely-ordered one.

---

# 34. DATABASE DESIGN

## Core Tables

```text
users
menu_categories
menu_items
tables
orders
order_items
reservations
reservation_foods
payments
feedback
```

The `users` table holds **admin accounts only** — no customer accounts exist, because customers do not log in.

No `reports` table is required because reports are generated on demand.

## Minimum Order & Reservation Fields (added)

These were referenced throughout the document but not previously enumerated at the field level, unlike menus/payments/feedback below.

```text
orders
------
id
order_number       (customer-facing reference, e.g. ORD-2026-00125 — shown on confirmation, receipt, and emails)
customer_name
customer_email
customer_phone
order_type         (dine-in / takeaway)
status             (pending / confirmed / preparing / ready / completed / cancelled)
total              (final amount payable — no subtotal, discounts, or taxes in V1 scope)
notes
created_at
updated_at

order_items
-----------
id
order_id
menu_item_id
item_name          (name at time of order — snapshot, so receipts remain accurate if the item is later renamed or deactivated)
quantity
unit_price         (price at time of order — snapshot, not a live reference)
created_at
updated_at

reservations
------------
id
table_id
customer_name
customer_email
customer_phone
reservation_type    (table_only / table_and_food)
date
time
guest_count
status              (pending / confirmed / completed / cancelled)
special_request
reservation_number   (e.g. RES-2026-00125 — the QR payload)
created_at
updated_at

reservation_foods
------------------
id
reservation_id
menu_item_id
item_name           (name at time of pre-order — snapshot, same rationale as order_items.item_name)
quantity
unit_price          (price at time of pre-order — snapshot, not a live reference)
created_at
updated_at
```

`unit_price` and `item_name` are stored as snapshots on `order_items` and `reservation_foods` so that a later price change or rename on `menu_items` never rewrites the amounts or names on a historical order or receipt.

---

# 35. MINIMUM MENU FIELDS

```text
id
menu_category_id
name
description
price
image
spice_level
protein_type
dietary_type
flavor_profile
is_available
is_featured
created_at
updated_at
```

---

# 36. MINIMUM PAYMENT FIELDS

```text
id
order_id nullable
reservation_id nullable
amount
status
fiuu_reference
created_at
updated_at
```

Payment status:

```text
pending
paid
failed
refunded
```

For reservation + food, payment must be associated with the relevant reservation/pre-order transaction.

---

# 37. MINIMUM FEEDBACK FIELDS

```text
id
order_id nullable
reservation_id nullable
food_rating
service_rating
experience_rating
comment
created_at
updated_at
```

Validation rule:

Exactly one of:

```text
order_id
reservation_id
```

must reference a completed transaction.

---

# 38. CORE RELATIONSHIPS

```text
Category
    1 ─── N
Menu


Order
    1 ─── N
OrderItem
    N ─── 1
Menu


Reservation
    N ─── 1
Table


Reservation
    1 ─── N
ReservationFood
    N ─── 1
Menu


Order
    1 ─── 1
Payment


Reservation
    0..1 ─── 1
Payment
```

Feedback may reference either:

```text
Order
```

or:

```text
Reservation
```

but not both at the same time.

---

# 39. LARAVEL ARCHITECTURE

Use Laravel MVC.

```text
Route
  ↓
Controller
  ↓
Service / Model
  ↓
Database / External API
  ↓
Controller
  ↓
Blade
```

External integrations should use service classes such as:

```text
app/Services/OpenAIService.php
app/Services/PaymentService.php
app/Services/ReceiptService.php
app/Services/ReservationService.php
```

Business logic must not be placed in Blade templates.

Do not place complex database logic in `routes/web.php`.

---

# 40. VIEW STRUCTURE

Customer layout:

```text
resources/views/layouts/master.blade.php
```

Admin layout:

```text
resources/views/admin/layouts/master.blade.php
```

Avoid duplicating:

- `<html>`
- `<head>`
- `<header>`
- `<footer>`

inside individual views.

---

# 41. COMPONENTIZATION RULE

Create a reusable Blade component when it:

- Is reused in at least two places.
- Contains conditional logic.
- Contains loop logic.
- Encapsulates styling and structure.
- Is likely to evolve independently.

Examples:

```text
button
food-card
status-badge
table-button
section-heading
```

Do not create components for trivial single-use wrappers.

---

# 42. RESPONSIVE REQUIREMENTS

The application must work on:

- Desktop
- Laptop
- Tablet
- Mobile

Customer functionality and admin functionality must both remain usable on smaller screens.

The system must not rely on desktop-only interactions.

---

# 43. ACCESSIBILITY REQUIREMENTS

Provide:

- Semantic HTML
- Form labels
- Keyboard navigation
- Visible focus states
- ARIA labels where appropriate
- Alternative text for images
- Clear validation messages
- State information not communicated by colour alone
- Reduced-motion support

Table availability must use both visual and textual indicators.

---

# 44. PERFORMANCE REQUIREMENTS

The system should target:

**Page load under 3 seconds under reasonable testing conditions.**

Optimize:

- Images
- Database queries
- Eloquent relationships
- Admin pagination
- Hero video

Hero video requirements:

- MP4/H.264
- Maximum approximately 5MB
- `preload="metadata"`
- Muted
- Loop
- Plays inline

If the hero video negatively affects performance, use a poster image or fallback instead.

Performance must be tested rather than assumed.

---

# 45. SECURITY REQUIREMENTS

Implement:

- CSRF protection
- Laravel validation
- Authentication
- Authorization
- Middleware
- Mass assignment protection
- Environment variables for secrets
- Secure payment verification
- Server-side price recalculation
- Server-side total recalculation

Never expose:

```text
OPENAI_API_KEY
FIUU_SECRET_KEY
SMTP credentials
```

Never commit `.env`.

Use `.env.example`.

## Guest Record Access Protection

Because there is no customer login, order and reservation identifiers are the only thing standing between a guest and their own record — and reservation numbers in particular are shown in a human-readable, sequential-looking format (`RES-2026-00125`). Without an additional check, anyone who can see or guess one identifier could view another customer's receipt (name, email, phone), cancel their reservation, or submit feedback in their name.

The following actions must use **Laravel signed URLs** (`URL::temporarySignedRoute`), not the bare identifier alone, as the access control:

- Viewing or downloading a receipt (`/orders/{order}/receipt`)
- Cancelling a reservation
- Opening the feedback form for a specific order/reservation

These signed links are what gets emailed to the customer (order confirmation, reservation confirmation, feedback invitation). A signature mismatch or expired link must return a generic "link no longer valid" error — not a hint about which part of the signature failed, and not a fallback that accepts the raw ID.

This does not require an account system — it requires that the emailed link, not the number printed on it, is the actual credential.

---

# 46. SAFE VS UNSAFE UI CHANGES

## Generally Safe

- Tailwind classes
- CSS
- Spacing
- Sizing
- Positioning
- Animation
- SVG/icon markup
- Visible text
- Blade presentation markup

## Unsafe Without Testing

- Form `name=""`
- Form `id=""` used by JavaScript
- Hidden input fields
- CSRF fields
- Form `action=""`
- JavaScript event handlers
- `data-*` attributes used by JS
- Route bindings
- Cookie names
- Session keys
- FIUU form fields
- FIUU hidden values
- OpenAI request parameters
- OpenAI system prompt
- QR-code payload structure
- QR verification logic

The purpose is to prevent UI redesign from breaking existing functionality.

---

# 47. ERROR HANDLING

The application must handle failures gracefully.

Required cases:

- Invalid form input
- Unavailable menu item
- No tables available
- Reservation conflict
- Order not found
- Payment failure
- OpenAI failure
- Email failure
- Unauthorized admin access
- Unexpected server errors

External service failure must not unnecessarily break unrelated functionality.

Examples:

If OpenAI fails:

```text
AI recommendations are temporarily unavailable.

You can continue browsing the menu.
```

If payment fails:

```text
Payment could not be processed.

Please try again or return to checkout.
```

---

# 48. LOADING STATES

Asynchronous operations should have visible loading states.

Examples:

- AI recommendation
- Payment processing
- Table availability
- Menu operations
- Report generation where appropriate

Loading states must prevent confusion without blocking normal interaction unnecessarily.

---

# 49. EMPTY STATES

Required examples:

### Empty Cart

```text
Your order is empty.

Explore the menu to add dishes.
```

### No Rasa AI Recommendations

```text
No dishes match your preferences.

Try adjusting your selections or browse the full menu.
```

### No Tables

```text
No tables available.

Please try a different date or time.
```

### No Admin Data

Show an appropriate explanation instead of an empty unexplained table.

---

# 50. VALIDATION

All customer and admin forms must use Laravel server-side validation.

Validate:

- Required fields
- Email
- Phone
- Date
- Time
- Guest count
- Menu item IDs
- Quantity
- Reservation availability
- Feedback ratings

Important values must never rely solely on browser-side validation.

---

# 51. DATA INTEGRITY

Historical records must remain meaningful.

Do not hard-delete records when historical references depend on them.

Examples:

- Deactivate menu items instead of deleting when referenced by orders.
- Mark reservations cancelled rather than deleting.
- Keep completed orders.
- Keep completed reservations.
- Keep feedback records.
- Keep payment history.

---

# 52. GIT REQUIREMENTS

Use Git throughout development.

Recommended commit structure:

```text
Initial Laravel setup
Create database migrations
Create models and relationships
Create seeders
Build customer layout
Build landing page
Build menu
Build cart
Build checkout
Build reservations
Build table availability
Add Rasa AI
Add FIUU
Add PDF receipts
Add email notifications
Build admin authentication
Build admin dashboard
Build admin management
Build reports
Build feedback
Testing and bug fixes
Final UI refinement
```

Do not commit:

```text
.env
/vendor
/node_modules
```

Commit:

```text
.env.example
```

---

# 53. TESTING REQUIREMENTS

## Customer Tests

Test:

- Landing page
- Contact Us page
- Contact form submission
- Rasa AI on Contact Us
- Menu
- Search
- Filters
- Rasa AI
- Cart
- Checkout
- Payment
- Confirmation
- PDF
- Email
- Reservation
- Table availability
- Table capacity
- Double-booking prevention
- Reservation cancellation
- Table + food
- QR code
- Feedback
- Reservation hold expiry (unpaid Table + Food reservation releases the table after 15 minutes)
- Receipt/cancellation/feedback links reject a guessed or tampered identifier without a valid signature

## Admin Tests

Test:

- Admin login
- Unauthorized access
- Dashboard
- Orders
- Reservations
- Tables
- Menu
- Categories
- Feedback
- Reports
- Reservation lookup/check-in by number or QR content, including rejection of a cancelled/completed reservation

## Security Tests

Test:

- Invalid inputs
- CSRF
- Unauthorized routes
- Invalid IDs
- Invalid quantities
- Invalid reservation times
- Client price manipulation
- Payment status manipulation
- Accessing a receipt, cancellation, or feedback link with a guessed ID and no valid signature
- Accessing a receipt, cancellation, or feedback link with an expired signature
- Two customers attempting to hold the same table/slot concurrently before payment

---

# 54. ACCEPTANCE CRITERIA

The project is complete when:

### Customer

- Customer can browse the restaurant.
- Customer can view the Contact Us page with restaurant details and submit an enquiry.
- Rasa AI recommendation is available on the Contact Us page.
- Customer can browse and filter menu items.
- Customer can use Rasa AI.
- Customer can add items to cart.
- Customer can complete checkout.
- FIUU payment can be verified.
- Customer can receive order confirmation.
- Customer can download PDF receipt.
- Customer can receive email.
- Customer can reserve a table.
- Customer can view correct table availability for the requested date/time.
- System prevents table double booking.
- Customer can reserve table + food.
- Customer can receive QR confirmation.
- Customer can cancel eligible reservations.
- Customer can receive post-dining feedback invitation.
- Customer can submit feedback for completed transactions.
- After submitting feedback, customer sees a thank-you page with a Google Review link.
- Customer's order type (dine-in/takeaway) is captured and shown to staff.
- An unpaid Table + Food reservation stops blocking its table/slot after the 15-minute hold expires.
- Receipt, cancellation, and feedback links only work as the signed link that was emailed — not by ID alone.

### Admin

- Admin can log in.
- Admin can view dashboard.
- Admin can manage orders.
- Admin can manage reservations.
- Admin can manage tables.
- Admin can manage menus.
- Admin can manage categories.
- Admin can view feedback.
- Admin can generate weekly reports.
- Admin can generate monthly reports.
- Admin can look up a reservation by number or QR content and the lookup rejects non-`confirmed` reservations at check-in.

### Technical

- Laravel application runs correctly.
- MySQL migrations run successfully.
- Seeders work.
- Eloquent relationships work.
- Validation works.
- Authentication works.
- Authorization works.
- OpenAI is safely integrated.
- FIUU is safely integrated.
- PDF generation works.
- Email works.
- QR generation works.
- Responsive design works.
- Accessibility requirements are addressed.
- No hard-coded secrets exist.
- Existing business logic is not broken by UI changes.

---

# 55. RISKS AND ASSUMPTIONS

| Risk / Assumption | Decision |
|---|---|
| FIUU sandbox credentials unavailable | Clearly labelled sandbox/mock flow allowed |
| OpenAI unavailable | AI is optional and ordering continues manually |
| Customer login not required | Guest ordering/reservation is the MVP |
| Single admin role | No role hierarchy required |
| Table turnover | Fixed 2-hour reservation duration |
| Reservation refund after payment | Manual admin handling |
| Hero video affects performance | Poster/fallback may be used |
| Advanced analytics | Out of scope |
| No customer login means no natural access control on guest records | Signed URLs (§45) act as the credential instead of an account |
| Unpaid Table + Food reservation could block a table indefinitely | 15-minute hold expiry, enforced via the availability query (BR-04) |

---

# 56. OUTSTANDING PRODUCT POLICY

The following policy may need supervisor/stakeholder confirmation:

### Reservation Refund Policy

The system supports manual admin handling of refunds for paid Reserve + Food cancellations.

The exact restaurant refund policy, such as:

- Cancellation cutoff
- Full refund
- Partial refund
- No refund

can be finalized without changing the core architecture.

---

# 57. BUILD ORDER

## Phase 1 — Foundation

- Laravel setup
- MySQL setup
- Migrations
- Models
- Relationships
- Seeders

## Phase 2 — Customer Core

- Layout
- Navigation
- Landing page
- Contact Us page
- Menu
- Search
- Filters
- Cart
- Checkout

## Phase 3 — Reservation

- Reservation form
- Time-window availability
- Table selection
- Double-booking prevention
- Table + food
- Cancellation
- QR confirmation

## Phase 4 — Integrations

- OpenAI
- FIUU
- PDF
- Email

## Phase 5 — Administration

- Admin authentication
- Dashboard
- Orders
- Reservations
- Tables
- Menu
- Categories
- Feedback

## Phase 6 — Reporting

- Weekly reports
- Monthly reports

## Phase 7 — Hardening

- Validation
- Security
- Accessibility
- Responsive testing
- Error states
- Loading states
- Empty states
- Performance testing
- Git cleanup
- Documentation

---

# 58. DEFINITION OF DONE

A release candidate is complete when:

1. The customer can complete the main ordering journey.
2. The customer can complete a table-only reservation.
3. The customer can complete a table + food reservation.
4. Table availability correctly respects the 2-hour reservation window.
5. Double booking is prevented.
6. Payment is verified server-side.
7. Receipts are generated.
8. Emails are sent.
9. QR reservations work.
10. Feedback invitation is sent after completion.
11. Feedback can only be linked to completed transactions.
12. Receipt, cancellation, and feedback access all require a valid signed link — an identifier alone is not sufficient.
13. An unpaid Table + Food reservation releases its table/slot after the 15-minute hold window.
14. Admin can manage all required data, including reservation lookup/check-in by number or QR content.
15. Reports generate correctly.
16. Rasa AI recommendations use only real menu items.
17. AI failure does not prevent normal ordering.
18. Sensitive credentials remain protected.
19. Mobile and desktop functionality work.
20. Core accessibility requirements are satisfied.
21. Contact Us page displays correctly and the enquiry form sends an email.
22. No unnecessary architectural complexity has been introduced.
22. The system can be explained and defended as a Final Year Project.

---

# 59. DESIGN SEPARATION

This PRD intentionally does NOT define:

- Colours
- Typography
- Fonts
- Hero visual treatment
- Animation style
- Button appearance
- Image treatment
- Decorative patterns
- Layout styling
- Video art direction

Those decisions belong in:

`UI_UX_BRIEF.md`

`UI_UX_BRIEF.md` is the visual source of truth.

This PRD is the product and functional source of truth.

The two documents must work together:

```text
PRD.md
"What does Le Miting do?"
        +
UI_UX_BRIEF.md
"How should Le Miting look and behave visually?"
```

Neither document should duplicate the responsibilities of the other.


# 60. FINAL PRODUCT VISION

Le Miting is a Laravel-based Malaysian restaurant ordering and reservation system that provides:

- Guest ordering
- Table reservation
- Food pre-ordering
- Rasa AI menu discovery
- FIUU payment
- Digital receipts
- Email notifications
- Reservation QR validation
- Post-dining feedback
- Google Review redirect after feedback
- Contact Us with Rasa AI
- Restaurant administration
- Weekly and monthly reporting

The core product identity is:

**Le Miting**

**Rasa Kampung, Cara Moden.**