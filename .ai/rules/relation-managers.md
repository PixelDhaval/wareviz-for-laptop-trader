---
paths:
  - 'app/Filament/**/Tables/**,app/Filament/**/RelationManagers/**'
---

# Relation Managers

## Bulk actions must use ->action(), never ->url(), for dynamic per-selection links
A Filament BulkAction's ->url(fn (Collection $records) => ...) is baked into the rendered href at the last table re-render, so quickly changing the checkbox selection can open a stale URL for a previously selected set of records — reproduced and confirmed with the "Print barcodes" bulk action. Fix: use ->action(function (Collection $records, Livewire\Component $livewire) { $livewire->js("window.open(...)"); }) instead — $records is resolved fresh at click time. See LaptopsTable::printBarcodesBulkAction() for the canonical implementation shared across the Laptop table and its relation managers.
