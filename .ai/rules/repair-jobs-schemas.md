---
paths:
  - app/Filament/Resources/RepairJobs/Schemas/RepairJobForm.php
---

# Repair Jobs Schemas

## RepairJobForm follows the same ExchangeRateFetcher pattern as Sale/ShipmentForm
RepairJobForm::expenseField()'s cost_currency_id Select defaults from Setting::current()->repair_cost_currency_id (a plain column, not per-enum-case like Shipment's) and, on change, calls the shared private updateExpenseExchangeRate() — same shape as SaleForm::updateExchangeRate()/ShipmentForm::updateCostExchangeRate(): fetch via ExchangeRateFetcher::rate() using the job's sent_at date, fall back to Currency::exchange_rate.

sent_at itself is NOT part of expenseField()'s FusedGroup — it's centralized in its own public static RepairJobForm::sentAtField() (a DatePicker with ->live()->afterStateUpdated() re-running updateExpenseExchangeRate(), plus a "Fetch exchange rate" suffixAction using $schemaGet/$schemaSet per the embedded-action gotcha). Every one of the 4 places a repair job is created/edited — RepairJobForm::configure(), Laptops' RepairJobsRelationManager, LaptopsTable::sendForJobAction(), ScanLookup::sendForJobAction() — must call RepairJobForm::sentAtField() instead of defining its own `DatePicker::make('sent_at')`, so the reactive wiring reaches all four in one place. Get/Set inside expenseField()'s and sentAtField()'s closures correctly read/write each other's fields even though they're separate top-level schema components (not nested in the same FusedGroup) — Get/Set resolve by field path across the whole schema, not by component nesting.

Setting::repair_cost_currency_id has no wrapper accessor (unlike saleCurrencyId()/shipmentCostCurrencyId(), which take an enum case) — it's read as a plain attribute since there's only one repair-expense currency default, no per-type dimension.

## Repair job expense is only asked for on completion, not on create
`RepairJobForm::expenseField()` is hidden unless `$record !== null && RepairJobForm::isBeingCompleted($get)` — the expense (cost/currency/rate) is never collected on the create form, only when editing a job and switching `status` to Completed (via the resource form, or the "Mark job complete" actions on LaptopsTable/ScanLookup/ViewLaptop, which pass `boundToRecord: false, costRequired: true`).

`->default()` never fires on Edit (Filament's own documented behavior), so `cost_currency_id`'s default from `Setting::current()->repair_cost_currency_id` would never apply once the field only shows up on Edit. `RepairJobForm::statusField()` works around this: its `afterStateUpdated()` manually re-applies the Setting default (and fetches the exchange rate) the moment `status` is switched to Completed and no currency is set yet. Always use `RepairJobForm::statusField()` for the status field instead of an inline `ToggleButtons::make('status')`.

`cost_exchange_rate` has a DB column default of `1` (so a freshly created job with no expense already has a non-null "1" in the column). To keep `required($hasAmount)` validation meaningful, the field has `afterStateHydrated()` that nulls it out on load when `cost_currency_id` is blank — otherwise the leftover DB default silently satisfies "required" instead of failing validation.
