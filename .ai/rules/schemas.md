---
paths:
  - 'app/Support/ExchangeRateFetcher.php,app/Filament/Resources/Sales/Schemas/SaleForm.php,app/Filament/Resources/Shipments/Schemas/ShipmentForm.php'
---

# Schemas

## Exchange rate auto-fetch: ExchangeRateFetcher + invoice date wiring
App\Support\ExchangeRateFetcher::rate(Currency $currency, CarbonInterface $date) fetches a HISTORICAL rate for that exact date from the free exchange-api (https://github.com/fawazahmed0/exchange-api, no key needed): tries jsdelivr's CDN first, falls back to the Cloudflare pages.dev mirror (per that repo's own warning), and returns null (never throws) on any failure — network error, unrecognised currency code, future date, or no base currency configured. Results are cached forever per (date, currency) pair since a published historical rate never changes.

This is wired into SaleForm (currency_id + sold_at) and ShipmentForm (each cost line's currency + the shipment-level `invoice_date` field, added specifically for this) via shared `updateExchangeRate()`/`updateCostExchangeRate()` helpers, each called from multiple `afterStateUpdated()` hooks (so changing EITHER the currency OR the date re-triggers the fetch). When the fetch returns null, it falls back to the currency's static `Currency.exchange_rate` — the exact previous behavior — so this is purely a smarter prefill, never a hard dependency; the fetched rate is still just a form default that gets snapshotted at save time like every other exchange rate in this app (see the Shipment/RepairJob snapshot note in models.md).

Shipment.invoice_date (nullable date, added specifically to drive this) is distinct from Shipment.received_at (when the shipment physically arrived) — one invoice date per shipment, shared by all 5 cost lines, matching how Sale already has one `sold_at`.
