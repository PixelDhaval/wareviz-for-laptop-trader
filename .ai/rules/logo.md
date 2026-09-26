---
paths:
  - 'app/Providers/Filament/AdminPanelProvider.php,resources/css/filament/admin/theme.css,public/images/logo/**'
---

# Logo

## WareViz branding: logo, favicon, and theme color live in AdminPanelProvider
The brand assets are in `public/images/logo/` (`wareviz_logo_long.svg`/`.png`, `favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`). `AdminPanelProvider::panel()` wires them up via `->brandLogo()`, `->favicon()`, and `->colors(['primary' => Color::hex('#3656D1')])` (the brand blue; the logo's orange accent is not applied to any semantic color to avoid clashing with Filament's default warning/danger palette).

This app has no separate public marketing site — the panel is mounted at the site root (`->path('')`), so it serves `/` directly (a guest hitting `/` is redirected straight to `/login`, not `/admin/login`). `routes/web.php` has no `/` route of its own. The default Laravel `welcome.blade.php` was deleted as dead code.

After changing `->colors()` or swapping a brand asset, run `npm run build` (or `composer run dev`) — the primary-color OKLCH palette is generated server-side from the hex and only shows up once the panel is rendered with fresh assets; Boost's Frontend Bundling rule applies here too.
