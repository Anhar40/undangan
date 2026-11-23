# AGENTS.md — Undangan Digital

## Commands
- Install/run: `composer install`, `cp .env.example .env`, `php artisan key:generate`, `php artisan migrate --seed`, `php artisan storage:link`, `php artisan serve`. Frontend: `npm install`, `npm run dev`, `npm run build`. `composer dev` runs serve + queue listener + pail + Vite together.
- Tests: `php artisan test`; focused: `php artisan test --filter=SecurityTest`, `php artisan test --testsuite=Feature`. `phpunit.xml` defaults to PostgreSQL `undangan_digital_test` (not SQLite). Create that DB before running tests; override `DB_CONNECTION`/`DB_DATABASE` via env if needed.
- Formatting: `vendor/bin/pint` (Laravel Pint, dev dep; no root `pint.json`).
- Useful ops: `php artisan invitations:expire-previews --prune-days=30`, `php artisan invitations:sync-nickname-slugs` (one-time for existing installs; old slugs 301). `db:seed` is insert-only/safe for reruns only where seeders are written that way — prefer `migrate --seed` for fresh installs.

## Architecture / where to look
- Laravel 12 / PHP 8.2, MySQL, Blade + vanilla JS + Tailwind v4 via Vite. `PRD.md` is the product source of truth; it is in Indonesian.
- Routes: `routes/web.php` (web), `routes/console.php` (scheduler: hourly preview expiry). Middleware aliases in `bootstrap/app.php`: `role`, `active`, `preview.expiry`; CSRF is exempt only for `payment/notification`.
- Structure: `app/Http/Controllers/{Admin,Customer,Public,Payment,Auth,Site}`, `app/Http/Requests/*`, `app/Services/{Invitation,Payment,Public,Dashboard,Admin}`, `app/Data`, `app/Enums`, `app/Policies`, `app/Contracts/PaymentGateway.php`, `app/Models`. Tests are mostly feature tests in `tests/Feature`.
- Template originals live in `template/tema1.html` and `template/tema2.html` and must not be redesigned/rewritten at runtime. Mirrored local assets are under `public/assets/templates/{tema1,tema2}`. Each invitation stores a frozen copy (`template_name`, `template_version`, `template_path`, `template_snapshot`) in `storage/app/template-snapshots`; admin template edits must not affect existing invitations.

## Template integration traps
- `tema1.html`/`tema2.html` are ~180–190 KB WordPress + Elementor Pro + WeddingPress dumps, not clean templates. Assets were originally hotlinked to `bukaundangan.com`, `fonts.googleapis.com/gstatic.com`, and music hosts; renderer rewrites remote URLs to local mirrored assets where available.
- Content is hardcoded inside Elementor markup (names, dates, venues, accounts, etc.) with no placeholder system. Integration is targeted string/`data-*` substitution, not variable interpolation.
- WP-only behavior must be reimplemented/neutralized in Laravel: guestbook/ajax endpoints (`WDP_WP.ajaxurl`, `cevar.ajax_url`, `wp-admin/admin-ajax.php`, nonces, `wp-json`), Elementor lightbox/gallery, audio player, countdown, copy-address. Strip WP boilerplate (Yoast/meta/JSON-LD, RSS/oEmbed, emoji, skip-link, `wp-*`) while preserving visual design, animations, and fonts.
- Countdown wiring differs: `tema1` uses `.elementor-countdown-wrapper[data-date]` with a Unix timestamp; `tema2` uses `.wpkoi-elements-countdown-items[data-date]` with a string like `Jul 09 2023 10:00:00` and inline `$("#...").countdown()`. Handle both.
- Templates need feature-flag config (story, gallery, music, theme colors, RSVP, families, etc.) to drive editor menus; some editor/API fields may not yet have customer UI controls.

## Product / implementation rules
- Keep role/status tokens literal: `Super Admin`, `Customer`; `DRAFT`, `PREVIEW`, `EXPIRED`, `PENDING_PAYMENT`, `ACTIVE`, `SUSPENDED`. Don't translate or rename them.
- No public registration. Super Admin is seeded/provisioned; roles are never chosen at login. Enforce ownership/policy checks — customers must never access another customer's invitations.
- Preview window is 10 hours from **draft creation, server time** (`preview_started_at`/`preview_expires_at`); content edits must not reset it. Expiry runs on-request via middleware and optionally by scheduler/command — do not rely on a daemon (shared hosting target).
- Activate invitations only after a **verified Midtrans webhook** (signature `sha512(order_id+status_code+gross_amount+server_key)`, order/amount match, idempotent). Never activate from redirect/success page. Status recheck uses the same verification path. `MIDTRANS_SERVER_KEY` stays server-side only.
- Invitation status and payment status are separate state machines.
- Public URL is `/u/{slug}` (nickname-based by default, old slugs 301 via `invitation_slug_redirects`) and supports `?to=Nama+Tamu` personalization.
- Money is stored as `DECIMAL(15,2)`; IDR is whole rupiah. Compare webhook amounts with decimal-safe comparison (`bccomp`), not floats.
- Keep business logic in services/controllers/requests/policies appropriate to the codebase; avoid piling logic into Blade or a single controller.