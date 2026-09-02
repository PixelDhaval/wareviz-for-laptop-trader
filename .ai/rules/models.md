---
paths:
  - app/Models/Laptop.php
---

# Models

## Laptop asset_code is generated post-insert, not pre-assigned
asset_code (format WV%06d, used for the printed barcode) can't be derived until the auto-increment id exists. Laptop::booted() sets a throwaway ULID in creating() to satisfy the NOT NULL unique column, then overwrites it with the real WV###### code in created() via saveQuietly(). Don't "simplify" this to a single creating() hook — the id isn't available yet at that point.
