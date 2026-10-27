---
paths:
  - 'app/Models/**'
---

# Models

## ReservationFood needs explicit $table (uncountable noun trap)
Laravel's pluralizer treats 'food' as uncountable, so a ReservationFood model resolves its table to 'reservation_food' (singular). Always declare `protected $table = 'reservation_foods';` or migrations/inserts fail with "no such table". Check any model whose last word is uncountable (food, equipment).
