---
paths:
  - 'app/Models/Setting.php,app/Filament/Pages/ManageSettings.php'
---

# Pages

## Setting is a singleton row accessed via Setting::current()
`Setting` holds app-wide UI defaults (default currency per SaleType and per ShipmentCostType) — never create a second row; always fetch/update via `Setting::current()` (firstOrCreate). `ManageSettings` is a Filament "singular resource" custom page (no SettingResource, no policy — pages in this app are open by default per relation-managers.md). Its currency Selects use `->relationship()` because the form is bound to a real record via `->record($this->getRecord())`, consistent with the project's relationship()-dehydration rule.

Consuming defaults in other forms: Filament's field `->default()` only applies on initial (no-data) form load, it does NOT re-run when a sibling field changes live. SaleForm's `currency_id`/`exchange_rate` therefore also use `afterStateUpdated()` on the `type` ToggleButtons to re-apply the matching sale type's default when the user switches type after the form has already loaded — copy this pattern if another field's default depends on a live sibling field.

## Setting also configures Laptop/Sale code generation format
Beyond currency defaults, Setting holds the format for auto-generating Laptop asset_code and Sale code (prefix/suffix/separator/date format/sequence pad, plus fiscal_year_start_month shared by both). See .ai/rules/support-models.md for how App\Support\CodeGenerator consumes these. The enum-cast columns (laptop_code_date_source, laptop_code_date_format, sale_code_date_format, sale_code_date_position) are cast to their enum classes in Setting::casts() — ManageSettings' Selects use plain ->options(EnumClass::class), not ->relationship(), since these are scalar columns, not FKs.

Gotcha already covered by the memory above but easy to reintroduce for a NEW settings column: any column relying on a migration-level default (not an explicit value) needs Setting::current()'s refresh-after-create to actually appear on the in-memory instance.
