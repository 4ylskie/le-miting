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
- Receive a reservation QR code for restaurant check-in.
- Submit feedback after completing an order or reservation.
- Contact the restaurant through the Contact Us page.

The administrator experience provides an operational interface for managing orders, reservations, tables, menus, categories, feedback, and reports.

---

## 2. UX Direction

The overall experience is inspired by modern restaurant websites such as the Tangerine restaurant template — the primary visual and UX reference.

The reference influences the experience through:

- Immersive restaurant presentation.
- Strong food-focused visual sections.
- Large, visually distinct content sections.
- Editorial-style composition.
- Prominent food imagery.
- Clear restaurant storytelling.
- Featured dishes and menu discovery.
- Testimonials and social proof.
- Strong calls to action.
- Restaurant information.
- A warm and welcoming hospitality-oriented experience.

The reference is an inspiration, not a template to reproduce. Le Miting retains its own:

- Malaysian restaurant identity.
- Kampung-inspired atmosphere.
- Restaurant content and menu.
- Ordering and reservation system.
- Rasa AI recommendation feature.
- FIUU payment flow.
- Feedback system.
- Administrative functionality.

The interface should feel like a real restaurant website first and a software system second.

---

## 3. Target Users

### 3.1 Guests (Customers)

Guests do not need registration, login, or accounts. They complete all primary tasks without creating a profile.

Primary guest tasks: discover Le Miting, browse the menu, use Rasa AI, order food, reserve a table, pre-order food with a reservation, pay via FIUU, view confirmations, access receipts, cancel eligible reservations, use the QR code at check-in, submit feedback, contact the restaurant.

### 3.2 Administrators

Administrators use a protected panel. The admin interface prioritises operational efficiency over immersive presentation.

Administrator tasks: monitor operations, manage orders, manage reservations, manage tables, manage menu items and categories, review feedback, generate reports.

---

## 4. Core UX Principles

**Simple** — Guests understand available actions without instructions. No unnecessary steps, forms, or navigation.

**Guest-friendly** — Order and reserve without an account. Collect only the information needed to complete the transaction.

**Food-focused** — Food is a major part of the experience. Menu items are presented with image, name, description, price, availability, and rating where available.

**Task-focused** — Order and Reserve are always easy to reach. Contact Us and Feedback remain accessible without competing with the primary actions.

**Clear feedback** — Loading, success, validation errors, payment failure, empty states, availability conflicts, and access failures all provide an explicit response.

**Consistent** — Buttons, forms, navigation, cards, status indicators, dialogues, loading states, and validation behave identically wherever they appear.

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
│   ├── Restaurant Experience
│   ├── Testimonials
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
└── Feedback
```

Navigation lets guests move between major areas without being forced through the order or reservation flow.

---

## 6. Landing Page UX

The landing page is the primary introduction to Le Miting. It creates an immersive restaurant experience in the spirit of the Tangerine reference while maintaining Le Miting's Malaysian and kampung-inspired identity.

### 6.1 Hero Section

Communicates: Le Miting branding, Malaysian food identity, a concise restaurant introduction, and primary actions.

May use a food-focused slideshow or immersive presentation. Provides clear access to **Order** and **Reserve**. The visual presentation must not delay reaching the main tasks.

### 6.2 Restaurant Introduction

A dedicated section communicating what makes Le Miting distinctive: Malaysian cuisine, traditional influence, restaurant atmosphere, heritage and authenticity, modern convenience.

### 6.3 Featured Dishes

Showcases selected menu items with enough information to understand the dish before entering the full menu. Provides a natural path toward browsing or ordering.

### 6.4 Restaurant Experience

Several short, visually distinct sections communicating the restaurant experience: authentic flavours, homemade food, kampung heritage, friendly atmosphere. Communicates the experience rather than overwhelming with text.

### 6.5 Testimonials

Social proof displayed clearly. Reviews identify the source where applicable. No likes, replies, or public discussion interface.

### 6.6 Order and Reserve CTAs

Both primary actions remain prominent throughout the landing experience. Labels describe the action clearly.

```text
Order Now
Reserve a Table
```

### 6.7 Restaurant Information

Address, phone number, email, and operating hours are available on the landing page and the Contact Us page.

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

The guest provides preferences such as spice level, protein, flavour, dietary preference, and beverage preference. Language is plain and guest-facing — no technical AI terminology.

### 9.2 Recommendation Results

Each recommended item displays:

```text
Food image
Name
Description
Price
Rating (where available)
Brief explanation of why the item was recommended
```

Guests can:
- Add recommended items to the cart.
- Retake the Rasa AI questionnaire.
- Continue to the full menu.

### 9.3 Rasa AI on Contact Us

The Rasa AI widget appears on the Contact Us page as a second entry point to the same feature. It behaves identically — same inputs, same results, same failure state. It is not a separate AI system.

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
Image
Name
Description
Price
Rating
Availability
```

Unavailable items are not presented as normally orderable. The interface clearly communicates when an item cannot be ordered.

---

## 11. Menu Search and Filtering

Supports: search by dish name, category filtering, menu attribute filtering (spice level, protein type, dietary type, flavour profile), popularity and rating filtering where supported.

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
QR Code
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
QR code
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
QR Code
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

## 21. Reservation QR Code UX

A QR code is generated after successful reservation confirmation.

**The QR code is used for:**
```text
Reservation validation
Customer check-in
Identifying the reservation for administrators
Supporting reservation and table status updates
```

**The QR code is not used for:**
```text
Payment
Feedback
Receipt retrieval
```

The QR presentation makes its purpose clear. The reservation number is also displayed as readable text — the guest is never dependent on the QR image alone.

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
Feedback form
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
Optional Rasa AI Widget
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

Displays: address, phone, email, operating hours.

Contact form fields: name (required), email (required), message (required).

The Rasa AI widget is optional — it does not block the contact form.

The enquiry does not create an order or reservation. It is sent to the restaurant by email. No guest-facing reply system exists.

**Submission failure:** preserve the entered form data so the guest does not re-type the message.

---

## 25. Feedback UX

Feedback is available only after an order or reservation is marked `completed`. The guest accesses the form through the signed link in the feedback invitation email — not by navigating directly.

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

**Feedback form:**

```text
Food rating: 1–5
Service rating: 1–5
Experience rating: 1–5
Comment (optional)
```

After submitting, the guest sees a thank-you page with an optional link to Le Miting's Google review page. The interface makes clear that the Google review is a separate, optional action and is not automatically submitted by Le Miting.

**Expired feedback link:**

```text
This feedback link is no longer valid.
```

No alternative route that accepts an arbitrary order or reservation number.

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
    ├── Feedback
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
Recent feedback
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

Administrators can: view reservations, search reservations, look up by reservation number, look up by QR content, confirm reservations, cancel reservations, mark reservations completed, view guest count, view selected table, adjust table assignment, view pre-ordered food.

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

## 34. Admin Feedback Management UX

Administrators can: view feedback, view food / service / experience ratings, view comments, view the linked order or reservation, view average ratings, filter feedback.

Feedback is presented as operational information, not a social feed.

---

## 35. Admin Reports UX

```text
Reports
├── Weekly Report  → Generate PDF → Download
└── Monthly Report → Generate PDF → Download
```

Covers: sales reports, reservation reports, feedback reports — weekly and monthly.

Reports are generated on demand. The interface communicates: report type, reporting period, generation status, successful generation, and generation failure.

The customer-facing website does not expose the reporting interface.

---

## 36. Navigation UX

**Customer navigation** — lightweight, persistent:

```text
Home
Menu / Order
Reserve
Contact Us
```

Feedback is accessed through the transaction-specific email link, not general navigation.

**Admin navigation** — separate from customer navigation, persistent access to all operational sections.

---

## 37. Buttons and Actions

Action-oriented labels:

```text
Order Now          Browse Menu       Get Rasa AI Recommendations
Reserve a Table    Add to Cart       View Cart
Proceed to Checkout   Pay Now        Confirm Reservation
Cancel Reservation    Submit Feedback   Send Enquiry
```

Primary actions are visually distinguishable from secondary actions. Destructive actions (cancellation) require confirmation where the action cannot easily be reversed. Submitted actions prevent accidental duplicate submission. Disabled actions communicate why they are unavailable where practical.

---

## 38. Forms UX

Forms: use clear labels, identify required fields, group related information logically, preserve entered values after recoverable failures, display validation errors near the relevant field, use understandable messages, never expose technical errors.

Browser-side validation improves speed but server-side validation is always enforced.

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
Menu grids            Menu cards            Filters and search
Cart                  Checkout forms        Reservation forms
Table selection       QR display            Confirmation screens
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
QR code visibility
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

The visual experience communicates: Malaysian culinary identity, kampung-inspired character, authenticity, warmth, hospitality, food quality, modern convenience.

The Tangerine reference influences composition and atmosphere through: large visual sections, food-first presentation, strong editorial hierarchy, immersive imagery, distinct content sections, prominent CTAs, restaurant storytelling, testimonials, featured-food sections.

Le Miting does not copy Tangerine's exact colours, typography, layout, images, text, animations, components, or branding. The final implementation must feel like Le Miting.

---

## 50. Motion and Interaction

Motion reinforces the restaurant experience. Appropriate uses:

```text
Hero transitions
Food image transitions
Section reveals
Hover feedback
Cart interactions
Loading feedback
```

Motion is subordinate to usability. Animations must not: delay important tasks, obscure information, prevent interaction, or create excessive movement. `prefers-reduced-motion` is always respected. The interface is fully understandable with animations disabled.

Animation duration: 150–300ms with ease-out. Loading feedback shows within 80ms of action.

---

## 51. Imagery and Food Presentation

Food imagery supports: menu discovery, featured dishes, restaurant identity, Rasa AI recommendations, landing-page storytelling.

Meaningful images have appropriate alternative text. Large images and hero media are optimised for performance. If video is used in the hero, an appropriate fallback exists for performance or accessibility reasons.

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
QR code purpose
```

Confirmation screens and emails prioritise these details.

---

## 54. Component UX Requirements

Reusable components behave consistently throughout the application:

```text
Navigation               Buttons                Forms
Input fields             Validation messages    Food cards
Featured food sections   Category filters       Search
Cart items               Quantity controls      Status badges
Alerts                   Confirmation dialogs   Table selection
Loading indicators       Empty states           Error states
QR display               Admin data tables      Rasa AI widget
```

Repeated components preserve consistent interaction behaviour while adapting layout for different screen sizes.

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

4. Reservation QR / Information

5. Reservation Cancellation

6. Contact Us
   ├── Restaurant Information
   ├── Optional Rasa AI Widget
   └── Contact Form

7. Feedback
   ├── Feedback Form
   └── Feedback Confirmation + Google Review Link

8. Guest Secure Access
   └── Signed-link receipt / cancellation / feedback entry points

9. Global States
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

8. Feedback
   └── Feedback List / Details

9. Reports
   ├── Weekly Sales Report
   ├── Monthly Sales Report
   ├── Weekly Reservation Report
   ├── Monthly Reservation Report
   ├── Weekly Feedback Report
   └── Monthly Feedback Report

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
- QR code is accessible and its purpose is explained.
- Eligible reservations can be cancelled.
- Receipt is accessible through the signed link.
- Feedback is only available after completion.
- Contact Us is easy to reach and use.
- Clear feedback is given when something fails.

### Administrator Experience

- Current restaurant situation is immediately understandable.
- Orders are easy to find and manage.
- Reservation status is clear.
- Table availability is clear.
- Menu management is straightforward.
- Feedback is easy to review.
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

Le Miting should feel like a modern Malaysian restaurant website with a strong food and hospitality identity — not a generic ordering application.

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
Post-Dining Feedback
```

The Tangerine restaurant template serves as the primary inspiration for the immersive, editorial, food-focused presentation. Le Miting retains its own Malaysian and kampung-inspired identity and all requirements defined in `PRD.md`.

```text
Make Le Miting feel like a restaurant first,
while making ordering and reservation effortless.
```