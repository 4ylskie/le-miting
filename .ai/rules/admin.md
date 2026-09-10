---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## Admin panel conventions
Admin controllers live in app/Http/Controllers/Admin, form requests in app/Http/Requests/Admin, views under resources/views/admin (layouts/app for authed, layouts/auth for login). Status updates are PATCH with forward-only transitions defined in a private $transitions() map; invalid moves return back()->with('error'). Guard user-gate decisions with explicit ValidationException::withMessages and a clear reason. Menu category deletes are blocked while MenuItem::withTrashed() items reference them.
