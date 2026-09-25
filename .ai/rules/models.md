---
paths:
  - app/Models/Laptop.php
  - app/Models/User.php
  - app/Models/Shipment.php
  - app/Models/RepairJob.php
  - app/Models/Sale.php
  - app/Models/Buyer.php
  - app/Models/Setting.php
---

# Models

## Laptop asset_code is generated post-insert, not pre-assigned
asset_code (format WV%06d, used for the printed barcode) can't be derived until the auto-increment id exists. Laptop::booted() sets a throwaway ULID in creating() to satisfy the NOT NULL unique column, then overwrites it with the real WV###### code in created() via saveQuietly(). Don't "simplify" this to a single creating() hook — the id isn't available yet at that point.

## User model must implement FilamentUser::canAccessPanel()
Filament's `Authenticate` middleware only allows a User model that does NOT implement `Filament\Models\Contracts\FilamentUser` when `app.env === 'local'`. In any other env (testing, staging, production) it aborts every panel request with a bare 403 — no policy/permission error, no useful message, just `abort_if(..., 403)` in `vendor/filament/filament/src/Http/Middleware/Authenticate.php`. This silently breaks the whole panel (and any Livewire/HTTP feature test that hits it) the moment APP_ENV isn't "local".

`User` implements `FilamentUser` with `canAccessPanel(Panel $panel): bool { return $this->roles()->exists(); }` — only users with at least one Spatie role can log into the admin panel; per-resource access is still governed by the generated Shield policies.

Tests that assert on a Filament resource/page need an authenticated user who satisfies `canAccessPanel()` — use `User::factory()->superAdmin()->create()` (see `database/factories/UserFactory.php`) rather than a bare `User::factory()->create()`.

## Shipment cost basis is computed, never stored; each cost line keeps its own exchange-rate snapshot
A shipment has 5 cost lines (App\Enums\ShipmentCostType: invoice_value, freight_cost, local_expense, duty, other_expense), each with an amount, a *_currency_id FK (restrictOnDelete) and its own *_exchange_rate to the base currency (Currency.is_base). The rate is a per-shipment snapshot on purpose: editing Currency.exchange_rate later must never change past shipment costs (it only prefills new shipments).

Shipment::total_cost and Shipment::average_cost_per_laptop are accessors, not columns: the average depends on the live laptop count (soft-deleted laptops excluded), so a stored value would go stale on every import or delete. Profit/loss work must read these accessors (eager-load withCount('laptops') to avoid N+1), not add cached columns.

Money math uses bcmath on decimal strings (8dp internally, rounded half-up to 2dp at the edge) - do not switch it to floats.

## RepairJob expense has its own currency + exchange-rate snapshot, same pattern as Shipment costs
RepairJob.cost has cost_currency_id (FK to currencies, restrictOnDelete) and cost_exchange_rate (decimal, default 1, DB default so old/no-currency jobs still convert as-is). Same reasoning as Shipment (see the Shipment note in this file): the rate is a per-job snapshot, never recomputed from Currency.exchange_rate after the fact. RepairJob::cost_in_base_currency is an Attribute (App\Support\Money::convert), not a stored column.

Any code that sums RepairJob.cost across rows (dashboard widgets, agency expense charts) must sum cost_in_base_currency in PHP instead — a SQL sum('cost') silently mixes currencies once a job isn't in the base currency. See App\Filament\Widgets\RepairExpenseByAgencyChart and InventoryOverview::monthlyRepairExpense() for the pattern (eager-load repairJobs, then Collection::sum(fn ($job) => $job->cost_in_base_currency)).

Laptop::purchase_cost/repair_expense_total/total_cost (computed Attributes, same "never stored" rule as Shipment) read RepairJob::cost_in_base_currency and Shipment::average_cost_per_laptop. LaptopResource::getEloquentQuery() eager-loads shipment (withCount laptops) + repairJobs.currency for this.

The expense form field (amount + currency + exchange-rate, in a FusedGroup) is shared via App\Filament\Resources\RepairJobs\Schemas\RepairJobForm::expenseField(bool $boundToRecord = true) — reused by RepairJobForm, Laptops' RepairJobsRelationManager, and (with boundToRecord: false, since they're bare Action::make()->schema() with no bound record — see the $get()/relationship() note above) ScanLookup and LaptopsTable's sendForJobAction. Add new shared fields there, not by copy-pasting into all four call sites again.

## Sale exposes two totals: total_sale_value (base currency) vs totalSaleValueInOwnCurrency() (the sale's own currency)
Sale::total_sale_value (an Attribute) is always in the base currency — used for cross-sale reporting where every sale must share one unit (SalesTable's "Total value" column, SaleInfolist). Sale::totalSaleValueInOwnCurrency() (a plain method, not an Attribute, to match Shipment::costInBaseCurrency()'s convention of methods for anything not a bare no-arg getter) converts that same base total back into the sale's own currency at its own stored exchange_rate — this is "what the bill actually says," used on ScanSale's "Total bill" display. Don't collapse these into one: they answer different questions and read differently once a sale's currency differs from the base (e.g. an Export sale in USD).

The base→other-currency conversion is App\Support\Money::convertFromBase() (bcdiv, the inverse of Money::convert()'s bcmul) — added specifically for this. If a rate is ever 0 (shouldn't happen: every currency/cost/price form enforces `gt:0`), it's treated as 1 rather than dividing by zero.

## Buyer is one table for both "Buyer" (Local sale) and "Consignee" (Export sale) — don't split it
App\Models\Buyer backs Sale.buyer_id regardless of Sale::type. The UI label switches per SaleType (SaleForm's buyer_id Select label: "Consignee" for Export, "Buyer" for Local) but the underlying record, table and BuyerResource are shared — this was an explicit choice, not an oversight. If asked to add consignee-specific fields (e.g. an export license number), add them nullable to this same table rather than creating a second model.

Sale.buyer_id is nullable and NOT required on the form (matches the field it replaced, buyer_name, which was also optional) — unlike Shipment.supplier_id, which IS required on the form despite also being a nullable column. That asymmetry is deliberate: supplier is a new field with no prior looser precedent, buyer preserves the sale form's existing looseness.

Shipment.supplier_id and Sale.buyer_id are both nullable at the DB level even though supplier is form-required, because both migrations ran against tables with pre-existing rows (shipments) or pre-existing free-text data that had to be backfilled (sales.buyer_name/buyer_contact → buyers, deduped by distinct pair — see add_buyer_id_to_sales_table's up()/down(), symmetric with add_generation_id_to_laptops_table's precedent).

## firstOrCreate() doesn't reflect DB column defaults — refresh() after create
Setting::current() uses firstOrCreate([]) to lazily create the singleton row, relying on migration-level column defaults (laptop_code_prefix 'WV', sequence pads, etc.) rather than duplicating them in PHP. Eloquent's create()/save() never re-reads DB-applied defaults back onto the in-memory model — a freshly-created instance holds null for every such column until re-fetched. current() calls ->refresh() when wasRecentlyCreated is true to fix this. If you add another DB-defaulted column to `settings`, don't rely on the in-memory attribute right after creation without this refresh.
