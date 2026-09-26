---
paths:
  - 'app/Models/Shipment.php,app/Filament/Resources/Shipments/Schemas/ShipmentForm.php,app/Models/Setting.php,app/Filament/Pages/ManageSettings.php'
---

# Filament Pages

## Shipment has a Local/Import type; local purchases use one single currency setting
`Shipment.type` (cast to `App\Enums\ShipmentType`: `Local`/`Import`, mirroring `SaleType`'s shape and `resolve()` helper) defaults to `Import` at the DB level (existing rows before this feature are all `Import`).

Unlike `ShipmentCostType`'s 5 independent per-line currency settings (`invoice_value_currency_id`, `freight_cost_currency_id`, etc. — each used only for `Import`), a `Local` purchase uses ONE single setting, `Setting.local_purchase_currency_id`, applied to every cost line. `Setting::shipmentCostCurrencyId(ShipmentCostType $type, ?ShipmentType $shipmentType = null)` takes the shipment type as a second, optional argument now — passing `ShipmentType::Local` short-circuits to `local_purchase_currency_id` regardless of `$type`.

`ShipmentForm`'s `type` field is a `live()` `ToggleButtons` whose `afterStateUpdated()` calls `refreshAllCostCurrencies()`, which re-defaults every cost line's currency (and re-fetches its exchange rate) for the newly-selected type — same pattern as `SaleForm`'s `type` → `saleCurrencyId()` re-application. `costLine()`'s per-field `->default()` closures also read `$get('type')` so a fresh Create form respects whichever type is already selected, not just `Import`.

`RepairJob::factory()`'s `cost` field uses `fake()->optional()`, so any test asserting "cost is required"/"cost is null" against a plain `RepairJob::factory()->create([...])` must pin `'cost' => null` explicitly — otherwise the assertion is flaky (~50% chance the factory already populated a random cost). This bit both `RepairJobResourceTest` and `ScanLookupSendForJobTest` — see [[repair-jobs-schemas]].
