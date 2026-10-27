# Le Miting — App Flow

> This document answers: **what screens exist, how does a user move between them, and what happens when something loads, fails, is empty, or they don't have permission?**
>
> Product requirements → `PRD.md`
> Architecture and integrations → `TECHNICAL_DESIGN.md`
> Visual design and component states → `UI_UX_BRIEF.md`
> Database and API details → `BACKEND_DESIGN.md`
> Build order and done criteria → `ENGINEERING_PLAN.md`

---

## 1. Overall Application Flow

```text
                        LE MITING
                            │
                   ┌────────┴────────┐
                   │                 │
               CUSTOMER            ADMIN
                   │                 │
        ┌──────────┼──────────┐      ▼
        │          │          │    Login
        ▼          ▼          ▼      │
     Browse      Order     Reserve   ▼
        │          │          │   Dashboard
        │          │          │      │
        │          ▼          ▼   ┌──┼──────────────────┐
        │       Checkout  Availability  │     │      │   │
        │          │          │   Orders  Reservations  │
        │          ▼          ▼      │     │      │   │
        │        FIUU       FIUU   Menus  Tables  Reports
        │          ▼          ▼
        │       Confirm    Confirm
        │          │          │
        │          ▼          ▼
        │       Receipt      Reservation number
        │          │          │
        │          └────┬─────┘
        │               ▼
        │           Completed
        │               │
        │               ▼
        │   Optional Google Review
        │       invitation email
        │
        ▼
    Contact Us
        │
        ▼
    Rasa AI
    + Enquiry Form
```

---

## 2. Customer Order Flow

```text
Landing Page
     │
     ▼
Browse Menu
     │
     ├── View Categories
     ├── Search / Filter Items
     └── Rasa AI (optional)
              │
              └── Select Recommended Items
     │
     ▼
Add Items to Cart
     │
     ▼
Review Cart
     │
     ├── Modify Quantity
     ├── Remove Items
     └── View Total
     │
     ▼
  Cart empty?
     │
     ├── Yes → Empty Cart State (cannot proceed)
     │
     └── No
          │
          ▼
       Checkout
          │
          ├── Enter Name
          ├── Enter Email
          ├── Enter Phone
          └── Select Order Type
                   │
                   ├── Dine-in
                   └── Takeaway
          │
          ▼
       FIUU Payment (or signed demo mock gateway in development)
          │
          ├── Payment Successful
          │        │
          │        ▼
          │   Laravel verifies server-to-server callback
          │        │
          │        ▼
          │   Order confirmed
          │        │
          │        ├── Generate Receipt (on demand)
          │        └── Send Order Confirmation Email
          │                  (includes signed receipt link)
          │        │
          │        ▼
          │   Order Confirmation Page
          │
          └── Payment Failed
                   │
                   ▼
              Payment Failure State
                   │
                   └── Retry the existing pending order / Return to Checkout
```

---

## 3. Customer Reservation Flow

### 3a. Table Only

```text
Landing Page
     │
     ▼
Choose Reservation Type → Table Only
     │
     ▼
Enter Reservation Details
     │
     ├── Date
     ├── Time
     └── Number of Guests
     │
     ▼
Check Table Availability
     │
     ├── Not Available
     │        │
     │        └── Select Another Date / Time
     │
     └── Available
              │
              ▼
         Select Table
              │
              ▼
         Enter Contact Details
              │
              ├── Name
              ├── Email
              └── Phone
              │
              ▼
         Confirm Reservation
         (status: confirmed immediately — no payment required)
              │
              ▼
         Assign readable reservation number
              │
              ▼
         Send Reservation Confirmation Email
         (includes reservation number + signed cancellation link)
              │
              ▼
         Reservation Confirmation Page
```

### 3b. Table + Food

```text
Landing Page
     │
     ▼
Choose Reservation Type → Table + Food
     │
     ▼
Enter Reservation Details
     │
     ├── Date
     ├── Time
     └── Number of Guests
     │
     ▼
Check Table Availability
     │
     ├── Not Available
     │        │
     │        └── Select Another Date / Time
     │
     └── Available
              │
              ▼
         Select Table
              │
              ▼
         Pre-order Food
              │
              ├── Browse Menu
              ├── Add Items
              └── Review Total
              │
              ▼
         Enter Contact Details
              │
              ├── Name
              ├── Email
              └── Phone
              │
              ▼
         FIUU Payment (or signed demo mock gateway in development)
         (reservation created as pending — slot is held for 15 minutes)
              │
              ├── Payment Failed
              │        │
              │        └── Payment Failure State → Retry the existing pending reservation
              │             Pending reservation hold expires after 15 minutes
              │             if payment is never completed
              │
              └── Payment Verified (server-to-server callback)
                       │
                       ▼
                  Reservation status → confirmed
                       │
                       ▼
                  Confirm readable reservation number
                       │
                       ▼
                  Send Reservation Confirmation Email
                  (includes reservation number + signed cancellation link
                   + receipt for food pre-order)
                       │
                       ▼
                  Reservation Confirmation Page
```

---

## 4. Reservation Cancellation Flow

Accessed via the signed cancellation link sent in the confirmation email.
A customer cannot cancel by navigating directly to a URL.

```text
Signed Cancellation Link (from email)
          │
          ▼
     Link valid?
          │
          ├── No (expired or tampered)
          │        │
          │        └── Generic "link no longer valid" error
          │
          └── Yes
                   │
                   ▼
              View Reservation Details
                   │
                   ▼
              Cancel this reservation?
                   │
              ┌────┴────┐
             No         Yes
              │          │
              ▼          ▼
           Return    Cancel Reservation
                          │
                          ├── Reservation status → cancelled
                          ├── Table slot released
                          └── Send Cancellation Confirmation Email
                          │
                          ▼
                     Cancellation Confirmed Page
```

**Note:** A `completed` reservation cannot be cancelled. If the signed link is valid but the reservation is already completed or cancelled, show a clear status message rather than a cancellation form.

---

## 5. Contact Us Flow

```text
Landing Page
     │
     ▼
Contact Us
     │
     ▼
View Contact Information
     │
     ├── Address
     ├── Phone
     ├── Email
     └── Operating Hours
     │
     ▼
Fill Contact Form
     │
     ├── Name (required)
     ├── Email (required)
     └── Message (required)
     │
     ▼
Submit Enquiry
     │
     ▼
Submission OK?
     │
     ├── No (validation error or mail failure)
     │        │
     │        └── Display inline error
     │             Form data retained — customer does not lose input
     │
     └── Yes
              │
              ▼
         ContactEnquiryMail sent to restaurant
              │
              ▼
         Success Message displayed
              │
              ▼
         End
```

---

## 6. External Google Review Invitation

When an order or reservation first reaches `completed`, Le Miting may send `GoogleReviewInvitationMail`.

```text
Order / Reservation marked completed
          │
          ▼
GoogleReviewInvitationMail sent
          │
          ▼
Customer optionally opens configured Google Review destination
          │
          ▼
Google receives and manages any submitted review
          │
          ▼
End
```

There is no local feedback form, database record, signed feedback link, review submission, or Google API call. The external destination does not include internal transaction identifiers.

---

## 7. Admin Flow

```text
/admin/* (any protected route)
     │
     ▼
Authenticated?
     │
     ├── No → Redirect to /admin/login
     │
     └── Yes
              │
              ▼
         Admin Dashboard
              │
              ├── Today's orders
              ├── Today's reservations
              ├── Today's revenue
              ├── Pending orders
              ├── Pending reservations
              └── Available tables
              │
     ┌────────┼────────────────────────────┐
     │        │        │         │          │
     ▼        ▼        ▼         ▼          ▼
  Orders  Reservations Tables   Menu      Reports
     │        │        │         │          │
     ▼        ▼        ▼         ▼          ▼
View      View     View      Add       Weekly / Monthly PDF
Orders  Reservations Tables  Item
     │        │        │         │
     ▼        ▼        ▼         ▼
View     Confirm/  Set       Edit
Customer  Cancel  Available/  Item
Details     │    Maintenance   │
     │      │                  ▼
     ▼      ▼              Remove Item
Update  Mark                    │
Status Completed            Manage
              │            Categories
              ▼
         Update Table
           Status
```

**Reports:**

```text
Reports
     │
     ├── Weekly Report  → Generate PDF → Download
     └── Monthly Report → Generate PDF → Download
```

---

## 8. Edge Cases and Error States

### 8a. Loading States

Applies where a server operation takes perceptible time. Show a visible loading indicator — do not leave the user looking at a static page.

```text
Operation triggered (Rasa AI / table check / payment / report)
     │
     ▼
Loading indicator shown
     │
     ├── Operation completes → Show result
     │
     └── Operation times out / errors → Show failure state (see relevant section below)
```

Applies to:
- Rasa AI request
- Table availability check
- FIUU payment processing
- PDF report generation

---

### 8b. Empty States

Show a clear explanation — never a blank page or an unexplained empty list.

**Empty cart:**
```text
Cart is empty
     │
     ▼
"Your order is empty. Explore the menu to add dishes."
     │
     ▼
Checkout button disabled / hidden
```

**No Rasa AI recommendations:**
```text
AI returns no matching items
     │
     ▼
"No dishes match your preferences.
Try adjusting your selections or browse the full menu."
     │
     ▼
Menu remains fully accessible — the dedicated Rasa AI page does not block ordering
```

**No tables available:**
```text
Availability check returns no results for requested slot
     │
     ▼
"No tables available for that date and time.
Please try a different date or time."
     │
     ▼
Customer returned to date/time selection
```

**Admin — empty data table:**
```text
No orders or reservations exist
     │
     ▼
"No records found." (or contextually appropriate message)
     │
     ▼
No empty unexplained table rendered
```

---

### 8c. Failure States

**Rasa AI failure:**
```text
Rasa AI / OpenAI API call fails (timeout / error / key issue)
     │
     ▼
"Recommendations are unavailable right now.
You can browse the full menu or try again."
     │
     ▼
Retry option shown
Menu remains fully browsable
Ordering continues without Rasa AI — Rasa AI is never a blocking dependency
```

**Payment failure (order or reservation):**
```text
FIUU payment fails or is declined (or the demo test control returns a verified mock failure)
     │
     ▼
"Payment could not be processed.
Please try again or return to checkout."
     │
     ├── Retry → Return to FIUU payment
     │
     └── Return to Checkout → Review details and try again

For Table + Food reservation:
     │
     ▼
Pending reservation hold expires after 15 minutes
     │
     ▼
Slot freed — reservation not confirmed
     │
     ▼
Customer must restart the reservation if they wish to try again
```

**Contact form submission failure:**
```text
Validation error or mail send failure
     │
     ▼
Inline error message displayed
Form data retained — customer does not lose input
     │
     ▼
Customer corrects and resubmits
```

---

### 8d. Permission and Access States

**Signed URL expired or invalid (receipt / cancellation):**
```text
Customer opens signed link (from email)
     │
     ▼
Laravel checks signature
     │
     ├── Valid → Show requested page
     │
     └── Invalid / expired / tampered
              │
              ▼
         Generic error: "This link is no longer valid."
              │
              ▼
         No hint about which part of the signature failed
         No fallback to raw ID access
         No login prompt
```

**Admin accesses protected route without authentication:**
```text
Request to /admin/* without valid session
     │
     ▼
EnsureAdminRole middleware fires
     │
     ▼
Redirect to /admin/login
     │
     ▼
(After successful login → original destination)
```

**Customer attempts to access admin route:**
```text
Request to /admin/* from non-admin session
     │
     ▼
EnsureAdminRole middleware fires
     │
     ▼
403 Forbidden
```

---

## REC-02B — Assessment maintenance flow

Admin authenticates → menu item edit → allergen assessments → select allergen → consult documented restaurant information → select independent recipe and cross-contact statuses → provide evidence and attestation → validate → lock menu item and reject stale context → transactionally save server-owned reviewer/time → display recorded statuses and provenance.

Missing or invalidated assessments → Unknown. Relevant admin menu metadata edit → transactional invalidation. Ingredient/supplier/preparation changes outside stored metadata → administrator records the change and invalidates existing assessments → new documented review required. There is no automatic expiry.

Under REC-02C, available items in active categories are evaluated per excluded code. A current, fully documented assessment governs when a row exists; stale, invalidated, incomplete, unknown, or risky rows exclude that item. Only items with no row use deterministic English/Malay matching against dish name and description, with explicit negations ignored. Unsupported allergy/intolerance requests and zero-eligible-item results show a staff-confirmation caution and make no provider call. Candidates are filtered before provider selection and returned IDs are checked again. Results show the shared-kitchen caution and either reviewed provenance or a menu-check badge; no result is described as allergy-safe.

## REC-02C — Customer allergen-exclusion flow

On `/ai/recommendations`, customer supplies `preferences_text` (0–300 characters) and/or selects one or more `excluded_allergens[]` from the ten canonical allergen codes. Require at least one non-blank text preference or valid selected code. Do not restore legacy structured preference selectors. Map explicit supported English/Malay allergen terms from text to canonical codes, then merge and deduplicate with selected codes. A generic/unspecified allergy or intolerance, or a term outside the controlled vocabulary, immediately branches to the staff-confirmation notice with zero provider calls.

For every selected or recognized code, start with available items in active categories. If a row exists, keep the item only when the assessment is current, has recipe status `reviewed_absent`, cross-contact status `reviewed_no_known_risk`, and complete reviewer/time/evidence provenance. Any stale, invalidated, incomplete, unknown, contains, or possible row excludes it. If no row exists, match canonical English/Malay ingredient terms against name and description; ignore explicit negative menu phrases, exclude positive matches, and allow non-matches with a menu-check badge. If no items remain, show an empty result with a staff-confirmation caution and make no provider call. Recheck every provider-returned ID with the identical rule.

When results are shown, display the shared-kitchen caution and instruct customers to inform staff before ordering. For each excluded code, show the `Reviewed:` badge only for a qualifying current assessment; show `Menu check: [allergen] not listed in dish name or description · Confirm with staff` when no assessment row exists. Menu matching does not verify ingredients or cross-contact. Relevant menu edits or explicit invalidation make an existing assessment ineligible until re-review. Never imply allergy safety.
