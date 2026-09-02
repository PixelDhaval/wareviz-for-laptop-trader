---
paths:
  - 'app/Filament/**'
---

# Filament

## Never compare $get() form state to a backed enum's ->value with ===
In this Filament version, `$get('someField')` inside a schema closure (visible()/required()/etc.) can return either the raw string or the actual enum instance depending on context — `$get('assignee') === JobAssignee::Agency->value` silently and permanently evaluates false when it returns the enum instance, which also means hidden fields never dehydrate (Filament excludes visible(false) fields from $data), so the bug shows up as "this field's value is always null after submit" rather than a visible error. Confirmed via Livewire test (`assertFormFieldVisible` failed even after `fillForm` explicitly set the driving field).

Fix: give the enum a `resolve(mixed $value): ?self` static helper (instanceof check, else `tryFrom((string) $value)`) and compare via `SomeEnum::resolve($get('field')) === SomeEnum::Case`. See `App\Enums\JobAssignee::resolve()` / `App\Enums\JobStatus::resolve()` for the pattern, used in ScanLookup's actions, LaptopsTable's sendForJobAction, and the RepairJob forms.

Separately: `Select::make(...)->relationship(...)` does not reliably dehydrate inside a bare `Action::make()->schema([...])` that has no bound Eloquent record (e.g. a page-level action or a table row action creating a new related record) — use `->options(fn () => Model::pluck('name', 'id'))` instead in that context. `->relationship()` is fine inside real resource/relation-manager forms that operate on a bound record.
