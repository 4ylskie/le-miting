---
paths:
  - 'config/**'
---

# Config

## Clear config cache after adding new config files
This dev environment has `bootstrap/cache/config.php` committed/stale. A new config file (e.g. config/payment.php) does NOT appear in config() until `php artisan config:clear` runs, causing silent null values (empty secrets, wrong driver branches). Always run config:clear after adding/changing config files on Windows dev.
