---
paths:
  - app/Models/Laptop.php
  - app/Models/User.php
---

# Models

## Laptop asset_code is generated post-insert, not pre-assigned
asset_code (format WV%06d, used for the printed barcode) can't be derived until the auto-increment id exists. Laptop::booted() sets a throwaway ULID in creating() to satisfy the NOT NULL unique column, then overwrites it with the real WV###### code in created() via saveQuietly(). Don't "simplify" this to a single creating() hook — the id isn't available yet at that point.

## User model must implement FilamentUser::canAccessPanel()
Filament's `Authenticate` middleware only allows a User model that does NOT implement `Filament\Models\Contracts\FilamentUser` when `app.env === 'local'`. In any other env (testing, staging, production) it aborts every panel request with a bare 403 — no policy/permission error, no useful message, just `abort_if(..., 403)` in `vendor/filament/filament/src/Http/Middleware/Authenticate.php`. This silently breaks the whole panel (and any Livewire/HTTP feature test that hits it) the moment APP_ENV isn't "local".

`User` implements `FilamentUser` with `canAccessPanel(Panel $panel): bool { return $this->roles()->exists(); }` — only users with at least one Spatie role can log into the admin panel; per-resource access is still governed by the generated Shield policies.

Tests that assert on a Filament resource/page need an authenticated user who satisfies `canAccessPanel()` — use `User::factory()->superAdmin()->create()` (see `database/factories/UserFactory.php`) rather than a bare `User::factory()->create()`.
