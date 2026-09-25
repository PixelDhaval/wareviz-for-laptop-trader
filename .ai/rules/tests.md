---
paths:
  - 'tests/**'
---

# Tests

## tests/Pest.php globally calls Http::preventStrayRequests()
Every Feature test now runs with Http::preventStrayRequests() active (added in the beforeEach() chained onto the RefreshDatabase binding in tests/Pest.php), because App\Support\ExchangeRateFetcher makes real outbound HTTP calls and several existing forms (SaleForm, ShipmentForm) trigger it via ordinary `afterStateUpdated()`/`fillForm()`/`set()` calls in tests — without this, those tests were silently reaching the real internet.

Any unfaked HTTP call in a test now throws instead of going out over the network. ExchangeRateFetcher's own try/catch swallows that throw and degrades to its normal Currency.exchange_rate fallback, so most existing Sale/Shipment tests need no changes — they just exercise the "API unavailable" path already. A test that wants to assert the successful-fetch path must call `Http::fake([...])` itself with a matching URL pattern (see ExchangeRateFetcherTest.php, SaleResourceTest.php, ShipmentResourceTest.php for examples) before triggering the relevant currency/date field change.
