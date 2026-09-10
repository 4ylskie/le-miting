---
paths:
  - routes/web.php
---

# Routes

## Register payment literal paths before the /payment/{payment} wildcard
Route::get('/payment/{payment}') (payment.create) binds the {payment} model param at match time. Literal paths like /payment/failed, /payment/return must be registered BEFORE it, or GET /payment/failed matches the wildcard and 404s with "no such payment". Keep literal > wildcard ordering for all resource routes that share a prefix.

## Admin login POST route lives in the guest group
POST /admin/login (admin.login.submit) must be inside the 'guest' middleware group, NOT the 'auth' group. When the user said "Continue to Payment" and later admin login broke, the login POST was placed under auth+ensure-admin so guests could never authenticate (redirect loop/419). Also, AuthController::login() explicitly checks Auth::user()->isAdmin() and rejects non-admins with a validation error instead of letting them reach the panel to 403.
