# Le Miting — UI/UX Brief

> This document answers: **what should the product look and feel like across every page, component, state, and screen size?**
>
> Product requirements → `PRD.md`
> Architecture and integrations → `TECHNICAL_DESIGN.md`
> Screen and system flow → `APP_FLOW.md`
> Database, models, APIs → `BACKEND_DESIGN.md`
> Build order and done criteria → `ENGINEERING_PLAN.md`

---

## Naming Rules

These names are fixed across all six documents. Do not substitute alternatives.

| Concept | Correct name |
|---|---|
| Payment gateway | FIUU |
| AI feature | Rasa AI |
| Admin role | admin / administrator |
| Customer | guest |

---

## 1. Purpose

Le Miting is a modern Malaysian restaurant website. The customer experience allows guests to:

- Discover the restaurant and its food.
- Browse the menu.
- Search and filter menu items.
- Receive optional Rasa AI food recommendations.
- Add food to a session-based cart.
- Place food orders as a guest.
- Reserve a table as a guest.
- Reserve a table and pre-order food.
- Make applicable payments through FIUU.
- Receive digital order and reservation confirmations.
- Access receipts and reservation information through secure links.
- Cancel eligible reservations.
- Receive a readable reservation number for restaurant check-in.
- Optionally follow a Google Review invitation after completion.
- Contact the restaurant through the Contact Us page.

The administrator experience provides an operational interface for managing orders, reservations, tables, menus, categories, and reports.

---

## 2. UX Direction

The customer experience draws visual and art-direction inspiration from [Atomix](https://www.atomixnyc.com/): restraint, editorial composition, sophisticated typography, generous negative space, strong photography, sparse navigation, asymmetric layouts, deliberate pacing, minimal decoration, and subtle interaction.

Atomix is an inspiration reference, not a template. Le Miting must not copy its branding, logo, wording, photography, layouts, artwork, or other proprietary assets.

Le Miting combines Atomix-inspired restraint with contemporary Malaysian hospitality and practical restaurant ordering UX. It remains recognisably Le Miting through its verified menu content, guest ordering and reservations, Rasa AI, FIUU payment flow, and administrative functionality.

The interface should feel like a composed restaurant experience first and a dependable transaction system throughout. It must not depend on unsupported restaurant history, testimonials, ratings, social proof, or fabricated business claims.

---

## 3. Target Users

### 3.1 Guests (Customers)

Guests do not need registration, login, or accounts. They complete all primary tasks without creating a profile.

Primary guest tasks: discover Le Miting, browse the menu, use Rasa AI, order food, reserve a table, pre-order food with a reservation, pay via FIUU, view confirmations, access receipts, cancel eligible reservations, use the reservation number at check-in, optionally follow an external Google Review invitation after completion, and contact the restaurant.

### 3.2 Administrators

Administrators use a protected panel. The admin interface prioritises operational efficiency over immersive presentation.

Administrator tasks: monitor operations, manage orders, manage reservations, manage tables, manage menu items and categories, and generate reports.

---

## 4. Core UX Principles

**Simple** — Guests understand available actions without instructions. No unnecessary steps, forms, or navigation.

**Guest-friendly** — Order and reserve without an account. Collect only the information needed to complete the transaction.

**Food-focused** — Food is a major part of the experience. Menu items present their real name, description, price, and availability. Real menu imagery is used when available; the layout remains deliberate and useful when it is not.

**Task-focused** — Order and Reserve are always easy to reach. Contact Us remains accessible without competing with the primary actions.

**Clear feedback** — Loading, success, validation errors, payment failure, empty states, availability conflicts, and access failures all provide an explicit response.

**Consistent** — Buttons, forms, navigation, content groupings, status indicators, dialogues, loading states, and validation behave identically wherever they appear.

**Responsive** — Usable across desktop, laptop, tablet, and mobile. Layouts adapt rather than shrink.

**Accessible** — Keyboard navigation, visible focus states, semantic HTML, form labels, ARIA labels, meaningful alt text, clear validation messages, non-colour status communication, reduced-motion support, usable touch interactions.

**Trustworthy** — Payment, reservation, and order information is always presented clearly. Guests always know what they are paying for, how much, whether payment succeeded, and whether their order or reservation is confirmed.

---

## 5. Customer Information Architecture

```text
Le Miting
│
├── Landing Page
│   ├── Restaurant Introduction
│   ├── Featured Dishes
│   ├── Verified Restaurant Introduction (when supplied)
│   ├── Editorial Food / Atmosphere Moment (when supplied)
│   ├── Order CTA
│   ├── Reservation CTA
│   ├── Contact Information
│   └── Opening Hours
│
├── Order
│   ├── Rasa AI
│   ├── Menu
│   ├── Categories
│   ├── Search
│   ├── Filters
│   ├── Cart
│   ├── Checkout
│   ├── Payment (FIUU)
│   └── Order Confirmation
│
├── Reserve
│   ├── Table Only
│   └── Table + Food
│
├── Contact Us
│
```

Navigation lets guests move between major areas without being forced through the order or reservation flow.

---

## 6. Landing Page UX

The landing page is the primary introduction to Le Miting. It uses an Atomix-inspired editorial pace while preserving rapid access to ordering, reservations, Rasa AI, and Contact Us. It may contain only verified restaurant content and approved assets.

### 6.1 Hero Section

Communicates: Le Miting branding, a concise verified introduction where supplied, and primary actions.

Use an immersive, typography-led hero with approved photography only when it is available. A static Full Warm Culinary CSS backdrop may sit behind the hero and page shell as ambient background art; it is not a substitute for photography or restaurant content. Do not use a slideshow, video, fabricated image, empty decorative media block, unrelated gradients, or emoji placeholder. Provide clear access to **Order** and **Reserve** without delaying the main tasks.

### 6.2 Restaurant Introduction

A restrained section for approved, verified restaurant information. If no verified introduction is available, omit this section rather than inventing history, heritage, chef, family, quality, or hospitality claims.

### 6.3 Featured Dishes

Showcases selected real menu items with enough information to understand the dish before entering the full menu. Use editorial media treatment when an approved item image exists; otherwise use a typography-led menu row without an artificial image box. Provides a natural path toward browsing or ordering.

### 6.4 Editorial Story or Atmosphere Moment

An optional, visually distinct moment may use approved restaurant photography or verified content to pace the landing page. It is omitted when no such content exists. It must not claim family history, heritage, chef biography, awards, service quality, or facilities unless those claims are verified and approved.

### 6.5 Content Evidence Boundary

The landing page does not display testimonials, customer quotes, reviewer names, ratings, review scores, awards, statistics, or other social proof unless a separately approved source and product requirement are added. Visual whitespace is not filled with fabricated facts.

### 6.6 Order and Reserve CTAs

Both primary actions remain prominent throughout the landing experience. Labels describe the action clearly.

```text
Order Now
Reserve a Table
```

### 6.7 Restaurant Information

Verified, configured address, phone number, email, and operating hours are available on the landing page and the Contact Us page. When they are not available, omit the facts rather than supplying placeholders.

---

## 7. Order Experience

```text
Order
   ↓
Rasa AI / Browse Menu
   ↓
Menu
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
Receipt + Email
```

No account required. The guest provides contact details at checkout, not before.

---

## 8. Order Entry

After selecting Order, guests choose:

```text
Order
│
├── Rasa AI
│
└── Browse Menu
```

Rasa AI is optional. Guests must always be able to bypass Rasa AI and browse the normal menu directly.

---

## 9. Rasa AI UX

Rasa AI assists menu discovery. It is clearly presented as optional and never required to complete an order.

### 9.1 Preference Inputs

The dedicated Rasa AI page has a labelled text field (maximum 300 characters), short example prompts, and a labelled group of checkboxes for the ten controlled allergen exclusions. Require at least one non-blank text preference or one selected allergen. Text may describe spice, protein, flavour, dietary preference, category, and a per-dish budget; legacy preference selectors are not shown. Explicit supported English/Malay allergen terms in text merge with selected exclusions. Unsupported, generic, or unmapped allergy/intolerance requests receive a clear staff-confirmation explanation and are not sent to a provider.

### 9.2 Recommendation Results

Each recommended item displays:

```text
Food image where available
Name
Description
Price
Brief explanation of why the item was recommended
```

Guests can:
- Add recommended items to the cart.
- Try another text request.
- Continue to the full menu.

When allergen exclusions are selected or recognized and results are returned, show the shared-kitchen caution and tell customers to inform staff before ordering. Current admin assessments govern when a row exists; only current reviewed-absent/no-known-cross-contact records qualify. Stale, invalidated, incomplete, unknown, contains, or possible rows exclude the dish. If no row exists, name and description terms are checked against the canonical English/Malay ingredient list with explicit negative phrases ignored; non-matches receive a `Menu check: [allergen] not listed in dish name or description · Confirm with staff` badge. Menu wording is not ingredient or cross-contact verification. Never label an item allergy-safe, allergen-free, or 100% safe. If no item qualifies, show no recommendations and a clear staff-confirmation caution; do not show unrelated fallback dishes or call a provider.

### 9.3 Dedicated Rasa AI page

The recommendation form (free text plus controlled allergen-exclusion checkboxes) is available on the dedicated page only. Contact Us does not embed a second recommendation form.

### 9.4 Rasa AI Failure

```text
Rasa AI recommendations are temporarily unavailable.

You can continue browsing the menu.
```

Guest can retry or continue to the normal menu. Rasa AI failure must never prevent ordering.

---

## 10. Menu UX

The menu is the primary food-discovery interface.

Guests can: browse categories, search by dish name, filter menu items, view featured items, view item details, add items to the cart.

Each menu item displays:

```text
Image where available
Name
Description
Price
Availability
```

Unavailable items are not presented as normally orderable. The interface clearly communicates when an item cannot be ordered.

---

## 11. Menu Search and Filtering

Supports: search by dish name, category filtering, and menu attribute filtering (spice level, protein type, dietary type, flavour profile).

Filters are easy to understand. When no items match, display a clear empty state with an action to broaden or clear the selection.

---

## 12. Cart UX

Persistent summary of the current order.

Guests can: add items, increase quantity, decrease quantity, remove items, clear the cart, review the total.

The cart displays:

```text
Item name
Quantity
Unit price
Item subtotal
Order total
```

The ordering cart and the reservation pre-order food selection are separate experiences. The interface must not imply that food selected in one flow has been added to the other.

**Empty cart:**

```text
Your order is empty.

Explore the menu to add dishes.
```

The guest is directed back toward menu browsing. The checkout button is disabled or hidden when the cart is empty.

---

## 13. Checkout UX

Focused and straightforward. The guest provides:

```text
Name
Email
Phone number
Order type (Dine-in / Takeaway)
Notes (optional)
```

The checkout summary displays: selected dishes, quantities, prices, guest information, order type, total amount.

The guest reviews the order before proceeding to FIUU payment. Important values are presented clearly and consistently.

---

## 14. Payment UX (FIUU)

FIUU is used for all applicable payments. The guest-facing experience communicates:

- Amount being paid.
- Payment provider (FIUU).

Development and demonstration environments instead show a visually distinct “Le Miting Demo Payment Gateway”. It prominently states that no funds are transferred, presents only fake method choices, supports success, failure, or abandonment, and never requests financial credentials. Its signed gateway access and mock-only verified result reproduce the application lifecycle without claiming FIUU protocol compatibility.
- Current payment state.
- Payment result.

Returning to the website does not by itself mean payment succeeded. The result shown reflects verified payment status, not browser navigation.

**Payment states:**

```text
Pending
Paid
Failed
Refunded
```

Payment state is always distinct from order or reservation status.

**Payment failure:**

```text
Payment could not be processed.

Please try again or return to checkout.
```

A clear recovery path is provided. The guest is never left uncertain about whether the transaction succeeded.

---

## 15. Order Confirmation UX

After successful order processing, the confirmation screen displays:

```text
Order number
Customer name
Order type (Dine-in / Takeaway)
Ordered items and quantities
Total
Payment status
Order status
Order date and time
```

The guest receives an email confirmation with a signed receipt link. The confirmation page prioritises transaction information over decorative content.

---

## 16. Reservation UX

The reservation experience immediately presents both types:

```text
Reserve
│
├── Reserve Table Only
│
└── Reserve Table + Food
```

Guests understand the difference before selecting. The difference (whether payment is required) is explained clearly before they begin.

---

## 17. Table-Only Reservation UX

Required information:

```text
Date
Time
Number of guests
Name
Email
Phone
Notes (optional)
```

Flow:

```text
Reservation Details
        ↓
Table Availability
        ↓
Select Available Table
        ↓
Customer Details
        ↓
Confirm Reservation
        ↓
Reservation Number
        ↓
Reservation Email
```

No payment required. After confirmation, the guest receives:

```text
Reservation number
Reservation date and time
Guest count
Assigned table
Reservation status
Reservation number
```

---

## 18. Table Availability UX

Simple, accessible selection interface. The guest does not need to understand the 120-minute overlap calculation.

Each table option communicates:

```text
Table number
Capacity
Availability status
```

**Guest-facing availability states:**

```text
Available
Selected
Booked for this slot
```

Maintenance tables are not selectable. The interface explains when a table is unavailable rather than silently disabling it. A table being unavailable for one slot does not imply unavailability for the whole day.

**No tables available:**

```text
No tables available.

Please try a different date or time.
```

The guest can change the date or time.

---

## 19. Table + Food Reservation UX

Food pre-ordering is part of the reservation process. Payment is required before confirmation.

Flow:

```text
Reservation Details
        ↓
Table Availability
        ↓
Select Available Table
        ↓
Pre-order Food
        ↓
Review Food Order
        ↓
FIUU Payment
        ↓
Payment Verification
        ↓
Reservation Confirmed
        ↓
Reservation Number
        ↓
Email Confirmation
```

The reservation remains `pending` during the payment stage. Confirmation only occurs after payment is successfully verified. The interface makes this clear — the guest must not believe the reservation is confirmed before payment completes.

---

## 20. Reservation Payment Hold UX

Selecting a table for a Table + Food reservation creates a 15-minute temporary hold while payment is completed.

The interface communicates the time limit where appropriate.

If payment is not completed within 15 minutes:

- The hold expires and the table is freed.
- The guest receives a clear failure or expiry state.
- The guest can restart the reservation process.

The interface must never imply that an expired unpaid reservation remains confirmed.

---

## 21. Reservation Identifier UX

A readable reservation number is displayed after successful reservation confirmation.

The reservation number supports reservation validation, customer check-in, and administrator lookup. It is not used for payment, receipt retrieval, or cancellation; those actions use their own protected flows.

---

## 22. Reservation Cancellation UX

Cancellation is accessed through the signed cancellation link provided in the confirmation email. A guest cannot cancel by navigating directly to a URL.

**Table Only:** cancellation is permitted before the reservation time, subject to the restaurant's policy.

**Table + Food:** cancellation before payment creates no refund issue. After payment, the guest cannot perform a self-service refund — refund handling is performed by the administrator.

A successful cancellation displays:

```text
Reservation number
Cancellation status
Relevant payment or refund information where applicable
```

---

## 23. Guest Record Access

Guests access transaction-specific information through secure signed links provided by the system. These links are sent by email.

Accessible via signed link:
```text
Receipt download
Reservation cancellation
```

The interface never suggests that a guest can access another guest's information by entering a sequential number.

**Expired or invalid link:**

```text
This link is no longer valid.
```

No technical detail about why the link failed. No fallback to raw ID access.

---

## 24. Contact Us UX

```text
Contact Us
    ↓
View Restaurant Information
    ↓
No embedded recommendation form
    ↓
Fill Contact Form
    ↓
Submit
    ↓
┌──────────────┐
│ Submission OK│
└──────┬───────┘
  Yes  │  No
       │  └──→ Display inline error
       │        (form data retained)
       ▼
  Success Message
       ↓
      End
```

Displays verified, configured address, phone, email, and operating hours when available. It does not show invented or placeholder business details.

Contact form fields: name (required), email (required), message (required).

Rasa AI remains separate from the contact enquiry form.

The enquiry does not create an order or reservation. It is sent to the restaurant by email. No guest-facing reply system exists.

**Submission failure:** preserve the entered form data so the guest does not re-type the message.

---

## 25. External Google Review Invitation UX

When an order or reservation first reaches `completed`, the customer may receive an email with a button to the configured external Google Review destination. The invitation clearly states that leaving a review is optional and happens on Google.

There is no local feedback form, thank-you page, signed feedback URL, ratings field, comment field, review-management page, or review report. Le Miting does not submit a review or retain Google review content.

---

## 26. Admin UX

The admin interface prioritises operational visibility, speed, and clarity. It does not attempt to reproduce the immersive customer-facing presentation.

```text
Admin
│
├── Login
│
└── Dashboard
    ├── Orders
    ├── Reservations
    ├── Tables
    ├── Menu Management
    ├── Categories
    └── Reports
```

---

## 27. Admin Login UX

Only administrators authenticate. Guests never see a login prompt.

The login interface provides: email field, password field, clear validation, clear authentication failure feedback.

Unauthenticated requests to `/admin/*` redirect to the login page. After successful login, the administrator is taken to their original destination.

---

## 28. Admin Dashboard UX

Operational snapshot:

```text
Today's orders
Today's reservations
Today's revenue
Pending orders
Pending reservations
Available tables
```

Helps administrators understand the current restaurant situation quickly. No complex analytics in V1.

---

## 29. Admin Order Management UX

Administrators can: view orders, view order details, view guest information, view ordered items, view order type, view payment status, update order status, mark orders completed, cancel orders where appropriate.

Order status and payment status are clearly distinguishable:

```text
Order Status:   Preparing
Payment Status: Paid
```

Payment status is system-controlled — administrators view it, they do not edit it.

---

## 30. Admin Reservation Management UX

Administrators can: view reservations, search reservations, look up by reservation number, confirm reservations, cancel reservations, mark reservations completed, view guest count, view selected table, adjust table assignment, view pre-ordered food.

**Reservation statuses:**

```text
Pending
Confirmed
Completed
Cancelled
```

For check-in, cancelled or completed reservations must not appear as valid active reservations.

---

## 31. Admin Table Management UX

Administrators can: add tables, edit tables, set capacity, place tables into maintenance, return tables to availability, view current reservation assignments.

Maintenance is clearly distinguished from normal availability. The interface prevents placing a table into maintenance when future reservations depend on it — unless those reservations are first reassigned or cancelled.

---

## 32. Admin Menu Management UX

Administrators can: add items, edit items, deactivate items, update prices, update descriptions, upload images, assign categories, set spice level, protein type, dietary type, flavour profile, featured state, and availability.

Active and unavailable items are immediately distinguishable. Historical items referenced by previous orders are deactivated rather than deleted.

---

## 33. Admin Category Management UX

Administrators can: add categories, edit categories, view items within a category, remove a category only when no items reference it.

If deletion is blocked because items still use the category, the interface explains the reason and provides the appropriate next step.

---

## 35. Admin Reports UX

```text
Reports
├── Weekly Report  → Generate PDF → Download
└── Monthly Report → Generate PDF → Download
```

Covers: sales and reservation reports — weekly and monthly.

Reports are generated on demand. The interface communicates: report type, reporting period, generation status, successful generation, and generation failure.

The customer-facing website does not expose the reporting interface.

---

## 36. Navigation UX

**Customer navigation** — sparse, persistent, and task-oriented. It uses a wordmark or text treatment until an approved logo exists. It contains no invented iconography or decorative controls.

```text
Home
Menu / Order
Reserve
Contact Us
```

**Admin navigation** — separate from customer navigation, persistent access to all operational sections.

On small screens, navigation collapses behind an accessible control with an accurate `aria-expanded` state and an `aria-controls` relationship. Cart access remains visible without competing with Order and Reserve.

---

## 37. Buttons and Actions

Action-oriented labels:

```text
Order Now          Browse Menu       Get Rasa AI Recommendations
Reserve a Table    Add to Cart       View Cart
Proceed to Checkout   Pay Now        Confirm Reservation
Cancel Reservation    Send Enquiry
```

Primary actions are visually distinguishable from secondary actions. Destructive actions (cancellation) require confirmation where the action cannot easily be reversed. Submitted actions prevent accidental duplicate submission. Disabled actions communicate why they are unavailable where practical.

Actions use the shared restrained button system: solid dark primary action, bordered or text-only secondary action, and an explicit destructive treatment. Buttons are not pill-shaped by default, do not rely on heavy shadows, and preserve visible focus and practical touch targets.

---

## 38. Forms UX

Forms: use clear labels, identify required fields, group related information logically, preserve entered values after recoverable failures, display validation errors near the relevant field, use understandable messages, never expose technical errors.

Browser-side validation improves speed but server-side validation is always enforced.

Forms use quiet borders, clear labels, generous vertical spacing, persistent visible focus, and field-associated error text. Transaction and reservation forms prioritise a linear reading order over dashboard-like panels.

---

## 39. Status Indicators

**Order:**
```text
Pending / Confirmed / Preparing / Ready / Completed / Cancelled
```

**Payment:**
```text
Pending / Paid / Failed / Refunded
```

**Reservation:**
```text
Pending / Confirmed / Completed / Cancelled
```

**Table (guest-facing):**
```text
Available / Selected / Booked for this slot
```

Status never relies on colour alone. Text labels or other accessible indicators accompany all visual status differences.

---

## 40. Loading States

Visible loading feedback for:

```text
Rasa AI recommendation generation
Table availability checking
FIUU payment processing
PDF report generation
Menu operations (where asynchronous)
Contact form submission
```

Loading indicators communicate that the system is working. Actions that could create duplicate transactions are temporarily protected from repeated submission. Loading states do not block unrelated navigation.

---

## 41. Empty States

Always explain what is missing and what the guest or administrator can do next.

**Empty cart:**
```text
Your order is empty.
Explore the menu to add dishes.
```

**No Rasa AI recommendations:**
```text
No dishes match your preferences.
Try adjusting your selections or browse the full menu.
```

**No tables available:**
```text
No tables available.
Please try a different date or time.
```

**No search results:** explain that no items match the current search or filters and provide an action to clear or modify.

**No admin data:** explain why a list is empty rather than displaying a blank interface.

---

## 42. Error States

The application provides user-friendly recovery states for:

```text
Invalid form input
Unavailable menu item
Empty cart
No available tables
Reservation conflict
Payment failure (FIUU)
Rasa AI failure
Contact form failure
Invalid order or reservation access
Expired signed links
Unauthorised admin access
Unexpected server errors
```

Errors explain what happened in plain language and provide a recovery action. Technical exceptions, stack traces, database errors, API keys, and internal details are never shown to guests.

---

## 43. Reservation Conflict State

If a table becomes unavailable between the guest's availability check and submission:

```text
This table is no longer available for the selected time.

Please choose another available table or time.
```

The guest is returned to the availability selection stage, not shown a generic system error.

---

## 44. Unavailable Menu Item State

If an item becomes unavailable after being added to the cart, the guest is informed before checkout. The interface: identifies the unavailable item, prevents checkout until resolved, allows removal, and never silently charges for or removes the item.

---

## 45. Access and Security UX

Guests use guest access. Administrators use authenticated access. The interface distinguishes these experiences clearly.

Guest transaction-specific access uses signed links from the system. If a link is invalid or expired:

```text
This link is no longer valid.
```

Never revealed: whether an identifier exists, which part of a credential failed, internal route information, database identifiers, security implementation details.

Unauthorised access to admin functionality results in a redirect to the admin login page or a 403 state.

---

## 46. Responsive Design

The entire application is usable across desktop, laptop, tablet, and mobile. Responsive behaviour is designed, not bolted on after.

Areas requiring particular attention:

```text
Navigation            Hero sections         Food imagery
Menu grids            Menu layouts          Filters and search
Cart                  Checkout forms        Reservation forms
Table selection       Reservation details   Confirmation screens
Admin tables          Admin navigation      Buttons and controls
Touch interactions
```

Complex multi-column layouts reorganise into readable single-column or stacked layouts on smaller screens.

---

## 47. Mobile UX

Mobile guests complete all primary tasks without desktop-only interactions. The mobile experience prioritises:

```text
Clear navigation             Large touch targets
Readable menu information    Easy quantity controls
Simple checkout              Easy reservation selection
Visible payment state        Accessible confirmation
Reservation number visibility
```

No horizontal scrolling for normal tasks.

---

## 48. Accessibility

- Semantic HTML.
- Proper form labels (`for` attribute).
- Full keyboard navigation.
- Visible focus states (2–4px focus ring).
- Appropriate ARIA labels on icon-only controls.
- Meaningful alternative text on images.
- Clear, field-associated validation messages.
- Non-colour status indicators (text labels accompany colour).
- `prefers-reduced-motion` support.
- Table availability communicated with both visual and textual indicators.
- Interactive elements have understandable accessible names.
- Animations never prevent task completion.
- Minimum touch target 44×44px with 8px spacing between targets.
- Contrast ratio ≥ 4.5:1 for normal text, ≥ 3:1 for large text.

---

## 49. Visual Direction

The customer-facing visual system is Atomix-inspired restraint + contemporary Malaysian hospitality + practical restaurant ordering UX. It favours editorial hierarchy, generous negative space, sparse navigation, asymmetric composition where it aids pacing, deliberate typography, thin dividers, and minimal interface decoration. A static Full Warm Culinary CSS backdrop with dark-glass functional surfaces is the approved ambient theme. It does not copy Atomix branding, content, photography, layouts, artwork, or proprietary assets.

### 49.1 Design Tokens

Use the following reusable customer-site tokens for the Full Warm Culinary Shader theme:

| Token | Value | Use |
|---|---|---|
| Shader base | `#16110E` | Deep espresso canvas and page foundation |
| Terracotta | `#975B3E` | Static ambient CSS gradient |
| Saffron amber | `#C87D4B` | Static ambient CSS gradient |
| Roasted clove | `#5C3020` | Static ambient CSS gradient |
| Secondary espresso | `#3E271E` | Static ambient CSS gradient |
| Dark glass | `rgba(30, 22, 18, 0.72)` | Functional navigation, forms, and grouped surfaces; blur 18px |
| Glass border | `rgba(216, 180, 148, 0.22)` | Quiet surface and field separation |
| Warm ivory | `#FAF6F0` | Primary text and high-contrast controls |
| Muted sand | `#D5CBBF` | Secondary text and metadata |
| Saffron accent | `#E7A26E` | Links, selected state, primary emphasis |
| Terracotta accent | `#C87D4B` | Secondary emphasis |
| Danger | `#A43C32` | Destructive action and error emphasis, paired with text |

The approved backdrop uses a deep-espresso base with static soft radial gradients in the listed terracotta, amber, clove, and secondary tones, filling the viewport edge to edge. It is the sole ambient gradient treatment; no unrelated gradients, neon, saturated green sections, purple AI styling, or multiple competing accents are permitted. Dark-glass surfaces use square-to-4px geometry and restrained borders, not stacked oversized cards. Color cannot be the sole communication of state. Text and controls must remain readable over the translucent surface.

### 49.2 Typography, Spacing, and Geometry

- **Display / H1 / H2:** `DM Serif Display`, a free/open editorial serif, weight 400. Use it only for display hierarchy.
- **H3, body, metadata, labels, controls:** Instrument Sans. Preserve its current role as the UI and body typeface.
- **Display:** `clamp(3rem, 7vw, 6.5rem)`, line-height 0.95–1.0.
- **H1:** `clamp(2.5rem, 5vw, 4.5rem)`, line-height 1.0.
- **H2:** `clamp(2rem, 3.5vw, 3.25rem)`, line-height 1.1.
- **H3:** 1.25rem, line-height 1.25; **body:** 1rem, line-height 1.6; **metadata:** 0.875rem; **eyebrow / label:** 0.75rem, restrained uppercase tracking.
- **Container:** maximum 80rem; gutters 1.25rem mobile, 2rem tablet, and 3rem desktop.
- **Section spacing:** `clamp(4.5rem, 8vw, 9rem)`; use smaller, deliberate spacing inside transactional screens.
- **Geometry:** 1px warm glass dividers; square to 4px radii; no default shadow; use a single subtle shadow only where elevation is essential.

Pages use whitespace and hierarchy; dark-glass panels are reserved for functional navigation, forms, and content groupings rather than decorative card stacking.

---

## 50. Motion and Interaction

Motion reinforces orientation and feedback without becoming a decorative feature. Appropriate uses:

```text
Subtle opacity or translate entry
Color and border feedback
Subtle food-image scale on hover
Cart interactions
Loading feedback
Static, low-amplitude ambient gradients behind content
```

Motion is subordinate to usability. Animations must not: delay important tasks, obscure information, prevent interaction, or create excessive movement. `prefers-reduced-motion` is always respected. The interface is fully understandable with animations disabled.

Control and feedback transitions use 150–300ms with ease-out. The ambient culinary backdrop is static for all users. Avoid autoplaying hero media, looping decorative UI animation, parallax, and motion that shifts reading position. Loading feedback shows within 80ms of action. `prefers-reduced-motion` disables non-essential transitions and animations.

---

## 51. Imagery and Food Presentation

Food imagery supports menu discovery, featured dishes, Rasa AI recommendations, and landing-page pacing only when it is approved and available. It is never fabricated, generated, hotlinked, downloaded as random stock photography, or replaced with an emoji, gradient, or fake image URL.

Where a runtime menu-item image exists, display it with an appropriate crop, meaningful alternative text, responsive dimensions, and performance-conscious loading. Where it does not exist, render a deliberate typography-led layout without an empty decorative media box. Large approved photography is responsive and optimised. Hero video is not part of the customer visual direction.

---

## 52. Content and Messaging

Guest-facing language is: clear, friendly, concise, restaurant-appropriate, non-technical.

The interface never exposes: API failure, database error, server exception, callback error, authentication token.

Instead:

```text
Payment could not be processed.
```

not:

```text
FIUU callback validation exception.
```

Consistent terminology is used throughout. Transaction information uses the same field names in the UI, emails, and confirmations.

---

## 53. Guest Trust Requirements

Guests must always be able to identify:

```text
What they selected
What they are paying
Order / reservation number
Current status
Reservation date and time
Guest count
Assigned table (where applicable)
Payment status
Reservation number purpose
```

Confirmation screens and emails prioritise these details.

---

## 54. Component UX Requirements

Reusable components behave consistently throughout the application:

```text
Customer navigation       Footer                 Editorial section heading
Primary / secondary action Form field            Validation message
Menu media treatment      Typography-led menu row Category filters / search
Cart item / summary       Quantity control       Status badge / alert
Table selection           Loading / empty / error state
Receipt-like confirmation Reservation detail      Rasa AI preference form
```

Repeated components preserve consistent interaction behaviour while adapting layout for different screen sizes. A universal card component is not required: use containers only when grouping, form affordance, or transaction clarity needs one.

---

## 55. Customer Screen Inventory

```text
1. Landing Page

2. Order
   ├── Rasa AI
   ├── Menu
   ├── Menu Search / Filters
   ├── Cart
   ├── Checkout
   ├── FIUU Payment
   └── Order Confirmation

3. Reservation
   ├── Reservation Type Selection
   ├── Reservation Details
   ├── Table Availability
   ├── Table Selection
   │
   ├── Table Only
   │   └── Reservation Confirmation
   │
   └── Table + Food
       ├── Pre-order Food
       ├── Food Review
       ├── FIUU Payment
       └── Reservation Confirmation

4. Reservation Information

5. Reservation Cancellation

6. Contact Us
   ├── Restaurant Information
   ├── No embedded recommendation form
   └── Contact Form

7. Guest Secure Access
   └── Signed-link receipt / cancellation entry points

8. Global States
   └── Loading / Empty / Error / Expired Link
```

---

## 56. Admin Screen Inventory

```text
1. Admin Login

2. Dashboard

3. Orders
   ├── Order List
   └── Order Details

4. Reservations
   ├── Reservation List
   └── Reservation Details

5. Tables
   ├── Table List
   └── Table Create / Edit

6. Menu Management
   ├── Menu Item List
   ├── Menu Item Create
   └── Menu Item Edit

7. Category Management
   ├── Category List
   └── Category Create / Edit

8. Reports
   ├── Weekly Sales Report
   ├── Monthly Sales Report
   ├── Weekly Reservation Report
   └── Monthly Reservation Report

10. Admin Global States
    └── Loading / Empty / Error / Unauthorised Access
```

---

## 57. UX Boundaries

This document defines the intended guest and administrator experience. It covers: user journeys, screen structure, information hierarchy, navigation, interaction behaviour, components, loading states, empty states, error states, access states, responsive behaviour, accessibility, content and messaging, restaurant atmosphere, high-level visual direction, food and image presentation, motion principles.

This document does not define: Laravel implementation, database schema, API architecture, business logic, payment callback implementation, authentication implementation, exact Tailwind classes, exact HTML, JavaScript architecture, exact backend routes, exact database queries, infrastructure configuration.

Those concerns belong in `TECHNICAL_DESIGN.md` and `BACKEND_DESIGN.md`.

---

## 58. UX Quality Criteria

### Guest Experience

- Guests understand what Le Miting is immediately.
- Order and Reserve are easy to find.
- Menu browsing requires no unnecessary navigation.
- Rasa AI is optional and clearly so.
- Rasa AI failure does not prevent ordering.
- Guests can order without creating an account.
- Cart review is clear before payment.
- Payment result is unambiguous.
- Table-only reservation completes without payment.
- Table + Food reservation completes with FIUU payment.
- Table availability is understandable.
- Reservation confirmation status is clear.
- Reservation number is clearly displayed and explained.
- Eligible reservations can be cancelled.
- Receipt is accessible through the signed link.
- Completed transactions may receive an optional external Google Review invitation.
- Contact Us is easy to reach and use.
- Clear feedback is given when something fails.

### Administrator Experience

- Current restaurant situation is immediately understandable.
- Orders are easy to find and manage.
- Reservation status is clear.
- Table availability is clear.
- Menu management is straightforward.
- Reports are easy to generate.
- Destructive actions require confirmation.
- Unauthorised users cannot access the admin panel.

### Responsive Experience

- Guest workflows work on desktop, tablet, and mobile.
- Admin functionality remains usable on smaller screens.
- No important functionality depends exclusively on hover.
- Forms are readable and usable at all breakpoints.
- Tables and data adapt to smaller screens.
- Touch interactions are practical.

### Accessibility

- Interactive controls are keyboard accessible.
- Forms have appropriate labels.
- Focus states are visible.
- Errors are understandable.
- Status information is not communicated by colour alone.
- Meaningful images have alternative text.
- `prefers-reduced-motion` is respected.

---

## 59. Final UX Definition

Le Miting should feel like a contemporary Malaysian restaurant website with restrained editorial hospitality — not a generic ordering application or a copied Atomix site.

The guest experience combines:

```text
Immersive Restaurant Presentation
              +
Food Discovery
              +
Simple Ordering
              +
Easy Reservation
              +
Optional Rasa AI
              +
Clear FIUU Payment
              +
Digital Confirmation
              +
Optional Google Review Invitation
```

Atomix informs restraint, editorial hierarchy, typography, photography-first pacing, sparse navigation, and subtle interaction. Le Miting retains its own verified content, identity, and all requirements defined in `PRD.md`. Unsupported history, testimonials, ratings, review scores, social proof, business facts, and imagery are omitted until verified and approved.

```text
Make Le Miting feel like a restaurant first,
while making ordering and reservation effortless.
```

---

## REC-02B — Admin allergen assessment interface

Existing menu edit pages link to a dedicated assessment list showing all ten allergens, separate recipe/cross-contact statuses, reviewer and UTC review time. Missing records show Unknown / Not reviewed. Stale or invalidated reviews show Unknown / review required, not a verified badge. The review form displays last evidence and invalidation provenance, requires documented evidence and an explicit attestation, and associates validation errors with labelled controls.

Admins record ingredient, supplier, recipe or preparation changes using the invalidation form. New reviews default invalidated statuses to unknown. Copy must distinguish reviewed absence from cross-contact assessment and must never promise allergy safety. REC-02C uses current assessments when present and a limited menu-text check only when no item/allergen row exists; it does not claim or imply allergy safety.

## REC-02C — Customer allergen-exclusion states

Use a clearly labelled “Avoid these allergens” checkbox group with the canonical ten names. Keep the text field optional when one or more exclusions are selected and require a selection when text is blank. Associate validation errors with their input/group and preserve safe submitted values. An unmapped/generic allergy request and a no-eligible-items result must be visibly distinct from provider failure, advise direct staff confirmation, and never display unrelated recommendations.

For qualifying results, show the shared-kitchen caution and a per-item factual badge for every selected allergen: reviewed absent from the recipe and no known cross-contact risk. Explain that this is based on admin-reviewed records, is not a guarantee of an allergen-free meal in an active kitchen, and customers must tell staff before ordering. Never use allergy-safe/allergen-free/100%-safe labels. Keep the checkbox group, caution, badges, errors, and results responsive, keyboard-operable, and clearly associated with their labels.
