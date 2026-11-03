---
paths:
  - 'app/Services/Payments/**'
---

# Payments

## Keep hosted Stripe sessions separate from offline simulation
A payment already bound to a hosted Stripe Session must never become a simulated payment when credentials are removed. Verify Stripe sessions server-side against the stored session ID, attempt reference, metadata, MYR amount and test mode before using PaymentApplier. Successful payments set Payment to paid and Order to confirmed; retain the legacy Fiuu and mock contracts for their existing tests.
