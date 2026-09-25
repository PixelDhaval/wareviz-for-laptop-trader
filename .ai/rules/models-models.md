---
paths:
  - 'app/Models/Laptop.php,app/Models/Sale.php,app/Models/SaleItem.php,app/Models/RepairJob.php'
---

# Models Models

## Laptop status: Sold vs Reserved is driven by the sale's completion, not by adding a sale item
Adding a laptop to a sale marks it `Reserved` if the sale is still a draft (`is_completed === false`), or `Sold` if the sale is already completed. `Laptop::saleContextStatus()` is the single source of truth for this — it never overrides `Defective`/`InRepair`, and returns `InStock` when there's no sale item.

`Sale::updated()` has a hook that, when `is_completed` changes, walks all its sale items and flips each laptop between `Sold`/`Reserved` (but only touches laptops currently `Sold` or `Reserved` — a laptop mid-repair/defective is left alone). `SaleItem::created()`/`deleted()` hooks handle the single-item add/remove case the same way.

`RepairJob::syncLaptopStatus()` restores the laptop's status via `$laptop->saleContextStatus()` when a repair finishes (not a hardcoded `InStock`), so a laptop that was `Reserved` before being sent for repair comes back `Reserved`, not `InStock`.

A laptop can be removed from a still-draft sale via `LaptopsTable::removeFromSaleAction()` (visible only when `Reserved` and the sale is not completed) or by deleting the sale item in `SaleItemsRelationManager` (its Delete actions are hidden once the sale is completed).
