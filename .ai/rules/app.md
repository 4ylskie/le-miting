---
paths:
  - 'app/**'
---

# App

## Feedback signed-link token & 24h expiry
Customer feedback uses signed links at GET/POST /feedback/{token}. Token format is 'order-{id}' or 'reservation-{id}' (type prefix required — the route has no separate type param). Links expire in 24h via URL::temporarySignedRoute. Only 'completed' transactions may be reviewed; the controller re-checks status even though the link is signed. FeedbackInvitationMail is dispatched from Admin\OrderController and Admin\ReservationController updateStatus when target is 'completed'.
