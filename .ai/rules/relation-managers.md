---
paths:
  - 'app/Filament/**/Tables/**,app/Filament/**/RelationManagers/**'
---

# Relation Managers

## Bulk actions must use ->action(), never ->url(), for dynamic per-selection links
A Filament BulkAction's ->url(fn (Collection $records) => ...) is baked into the rendered href at the last table re-render, so quickly changing the checkbox selection can open a stale URL for a previously selected set of records — reproduced and confirmed with the "Print barcodes" bulk action. Fix: use ->action(function (Collection $records, Livewire\Component $livewire) { $livewire->js("window.open(...)"); }) instead — $records is resolved fresh at click time. See LaptopsTable::printBarcodesBulkAction() for the canonical implementation shared across the Laptop table and its relation managers.

## A relation-manager's child model needs either its own Shield policy or explicit ->authorize() on its actions
Every relation-managed child model in this app so far also has its own Filament Resource (Laptop, RepairJob, LaptopModel, ...), so it also has a generated Shield policy, and CreateAction/DeleteAction "just work" via Filament's default per-model-policy authorization. App\Models\SaleItem broke that pattern: it has no Resource of its own (only App\Filament\Resources\Sales\RelationManagers\SaleItemsRelationManager), so no Create:SaleItem/Delete:SaleItem permission is ever generated — and Filament's default authorization then DENIES those actions outright, even for super_admin (confirmed: `$user->can('create', SaleItem::class)` → false with no policy registered, since there's no blanket Gate::before bypass here — super_admin's access comes entirely from having every existing permission row synced to its role, and no SaleItem permission rows exist).

The fix is NOT overriding RelationManager::canCreate()/canDelete()/canDeleteAny() or getCreateAuthorizationResponse()/getDeleteAuthorizationResponse() — none of those are actually consulted for action visibility (confirmed by instrumenting them: never called). The fix is calling ->authorize(fn () => ...) directly on the action itself (CreateAction/DeleteAction/DeleteBulkAction in ->headerActions()/->recordActions()/->toolbarActions()). See SaleItemsRelationManager, which gates create/delete on the OWNER record's own "update" permission (auth()->user()->can('update', $this->getOwnerRecord())) rather than inventing a separate SaleItem permission nobody would configure in the Shield roles UI — appropriate whenever the child model is a pure join/line-item with no reason to be permissioned separately from its parent.

Contrast with App\Filament\Resources\Sales\Pages\ScanSale (a custom resource page, not a relation manager): Filament\Pages\Concerns\CanAuthorizeAccess::canAccess() defaults to `true` (open to any panel user) when not overridden — the opposite failure mode. Check both ends when adding a model with no Resource: custom pages fail open, relation-manager actions on a policy-less model fail closed.
