---
paths:
  - 'app/Support/Reports/**'
---

# Support Reports

## FinancialReportData: the single money-safe aggregation source for Reports
App\Support\Reports\FinancialReportData centralizes every cross-row money total the Reports pages need (containerProfit, monthlyRevenueAndExpense, expenseByCategory, transactions) so the PHP-sum-never-SQL-sum currency rule (models.md) is only implemented once, not duplicated across 10 widget classes. Each report widget calls into this class and formats the result; it never re-derives aggregation logic itself.

Two distinct, deliberately different profit concepts coexist — don't try to unify them:
- containerProfit() = itemized realized profit: only SOLD laptops count (via Laptop::profit/sale_price/total_cost), netting each laptop's own historical purchase cost against its sale, regardless of which month the shipment was purchased in. Unsold stock contributes 0 until it sells.
- monthlyRevenueAndExpense()'s `net` = cash-flow-style: revenue recognized at Sale::sold_at, expense recognized at Shipment (invoice_date ?? received_at) / RepairJob.sent_at, for whichever month those dates fall in — independent of when the sold laptop was originally purchased.

App\Support\Money::roundSignedToCents() (added alongside FinancialReportData) must be used instead of roundToCents() for any total that can legitimately be negative (profit, net) — roundToCents() is documented non-negative-only and rounds a negative amount the wrong way (adds 0.005 then truncates, e.g. -50.556 -> -50.55 instead of -50.56).

Gotcha already hit once: Database\Factories\SaleItemFactory's definition() creates a throwaway Sale (and via its own laptop_id default, a throwaway Laptop+Shipment) as a side effect of computing defaults, even when ->for($sale) overrides sale_id — the throwaway rows still exist. Any test asserting an UNBOUNDED (null, null) FinancialReportData query must either scope the date range or use ->sole()/filtered lookups, not raw counts/exact-array equality against the whole result set.
