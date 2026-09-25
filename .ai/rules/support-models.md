---
paths:
  - 'app/Models/Laptop.php,app/Models/Sale.php,app/Support/CodeGenerator.php,app/Models/CodeSequence.php'
---

# Support Models

## Laptop asset_code and Sale code are generated via App\Support\CodeGenerator, not user input
Both are fully automatic — never exposed as editable form fields (SaleForm's 'code' TextInput is ->disabled()->dehydrated(false), visible only when editing an existing record, just to display it). App\Support\CodeGenerator::forLaptop()/forSale() reads format settings (prefix/suffix/separator/date format/sequence pad) from Setting::current() and assembles the code from a sequence number pulled from App\Models\CodeSequence::next($type, $periodKey) — an independent, period-scoped counter (NOT derived from the record's id, unlike the old hardcoded WV%06d). periodKey is the code's own formatted date segment (or '' for a global, never-resetting counter), so the sequence naturally resets whenever that segment's value changes (e.g. a new month).

Laptop::booted()'s created() hook force-fills asset_code (same two-phase placeholder pattern as before — see the existing note above). Sale::booted()'s creating() hook uses `$sale->code ??= CodeGenerator::forSale($sale)` — the ??= (not unconditional overwrite) is deliberate: it lets factories/tests still set an explicit code via Sale::factory()->create(['code' => ...]) for setup convenience, since the real UI can never supply one anyway (the form field is disabled). SaleFactory's definition() does NOT set 'code' by default, so plain factory calls exercise the real generator.

See .ai/rules/pages.md for the Setting-side format configuration.
