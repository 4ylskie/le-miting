---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Flash input when a controller renders a two-step form directly
GET controllers that render a view directly (no redirect) do NOT populate old() — hidden fields / values that read old() come back empty and the next POST fails validation. Call $request->flash() before returning the view (see ReservationController::availability). Never rely on old() in a view after a plain GET render unless the controller flashed it.
