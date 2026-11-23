# MIGRATION_AUDIT_REPORT.md

## A. Migration Summary

### Scope
This pass audited and migrated the existing Laravel 12 INVITE application toward:

- **PostgreSQL** as the primary database
- **Cloudinary** as primary user/app-managed media storage
- **Vercel** serverless deployment readiness
- **GitHub** readiness (`.gitignore`, `.env.example`, README, secrets hygiene)

The work was performed as infrastructure/compatibility migration only. Existing business flows, role/status tokens, UI, and invitation template designs were preserved where code was touched.

### What Was Migrated / Changed

- **Database config:** `config/database.php` default changed to `pgsql`; PostgreSQL connection made env-aware (`DATABASE_URL`, `DB_SSLMODE`, `DB_SCHEMA`/search path). Duplicate pgsql block removed. Legacy MySQL connection retained as explicit non-default connection.
- **Queue config:** `config/queue.php` batching/failed DB defaults changed to `pgsql`.
- **Filesystem config:** Added `cloudinary` disk in `config/filesystems.php`. `.env.example` now defaults to `FILESYSTEM_DISK=cloudinary` and includes `CLOUDINARY_URL`.
- **Media storage abstraction:** Added `App\Services\Storage\MediaStorageService` for Cloudinary upload/delete/URL resolution with local-public fallback when Cloudinary is not active. Implementation uses Cloudinary HTTP API directly (no package installed).
- **Media call sites integrated:** `InvitationEditorService`, `MusicController`, `TemplateManagementService`, `TemplateSnapshotService`, `InvitationExpiryService`, and gallery dimension handling in `InvitationDataService`.
- **Model/URL compatibility:** `Music`, `InvitationGallery`, `InvitationSetting`, `InvitationGiftAccount`, `Template` already had or were made compatible with http(s) URLs; Cloudinary URLs resolve directly. `Template::contents()` added for http/local template source reading.
- **Template/snapshot Vercel compatibility:** `TemplateSnapshotService` stores snapshots in Cloudinary when active and can read snapshot http URLs. `TemplateRenderer` uses `Template::contents()`.
- **PostgreSQL compatibility fixes:** Fixed `DATE_ADD` in active-expiry migration to be driver-aware (`interval '1 year'` on pgsql). Replaced admin search `LIKE` queries with case-insensitive `LOWER(... ) LIKE ?` raw queries for PostgreSQL behavior consistency.
- **Vercel prep:** Added `vercel.json`, `api/index.php` serverless entrypoint, and `/tmp` storage/bootstrap cache handling in `bootstrap/app.php`. Added `trustProxies('*')` for serverless proxy handling.
- **GitHub prep:** Updated `.gitignore`, rewrote `.env.example` with placeholders only, created professional `README.md`.
- **Tests config:** `phpunit.xml` changed from MySQL to PostgreSQL default (`DB_CONNECTION=pgsql`, `undangan_digital_test`).

### Dependencies / Packages

- **Added:** none in `composer.json`/`composer.lock`.
- **Attempted:** `composer require cloudinary-labs/cloudinary-laravel --no-interaction` timed out in this session; no package added.
- **Reasoning:** Implemented `MediaStorageService` using Laravel HTTP client against Cloudinary REST API to avoid blocking on package install and to keep dependency surface small. If you prefer the official package, install and adapt the service.

## B. PostgreSQL Audit

### Incompatibilities Discovered / Addressed

- **MySQL-specific date function in migration:** `database/migrations/2026_11_23_000003_add_active_expires_at_to_invitations_table.php` used `DATE_ADD(...)` for backfill. Fixed to driver-aware expression (`activated_at + interval '1 year'` for pgsql, `DATE_ADD` for MySQL). Note: this migration had already been run in the environment before this edit; fresh installs will use the updated migration.
- **Default DB connection/config:** config default was MySQL; changed to PostgreSQL. Queue batching/failed defaults were MySQL; changed to pgsql.
- **Case-insensitive search behavior:** Admin search filters used `LIKE` with interpolated values; on PostgreSQL `LIKE` is case-sensitive. Updated admin controllers to `LOWER(column) LIKE ?` with bound lowercased search term for cross-db case-insensitive behavior.
- **Enum/json/boolean/timestamp/unsigned types:** reviewed migrations. Laravel schema builder mappings for these types are generally PostgreSQL-compatible in Laravel 12; no destructive migration rewrites performed. Existing enum columns (e.g., vouchers `type`) rely on Laravel's schema abstraction.
- **Raw SQL scan:** app code had no `DB::raw/whereRaw/selectRaw` MySQL-specific patterns found by scan except migration fix; admin LIKE changes introduced intentional `whereRaw(LOWER...)` which is PostgreSQL-compatible.

### Remaining PostgreSQL Risks / Follow-up

- **Not fully validated against a live PostgreSQL database in this session.** No fresh PostgreSQL migration/test run was executed here.
- **Full migration history:** existing migrations were not rewritten en masse; only targeted incompatibility fixed. A fresh `migrate:fresh` on PostgreSQL should be run in CI/staging before production cutover.
- **Case-sensitive uniqueness/collation differences:** PostgreSQL text comparisons differ from MySQL collations. Functional behavior should be regression-tested, especially around slug/email/code lookups.
- **Connection pooling:** `DATABASE_URL` is supported; for Neon/Supabase/pgBouncer, verify pooling mode (transaction pooling can affect prepared statements/session features). SSL mode is env-configurable.
- **phpunit PG DB:** `phpunit.xml` now defaults to `pgsql`/`undangan_digital_test`; the database must exist and be accessible for tests.

## C. Cloudinary Audit

### Upload Locations Migrated / Integrated

- **Invitation editor uploads:** `InvitationEditorService::store()` now uploads via `MediaStorageService` to Cloudinary when active; delete/rollback/obsolete cleanup uses `MediaStorageService::delete()`.
- **Admin music:** `MusicController` upload/replace/delete uses `MediaStorageService`.
- **Admin template thumbnails / managed template HTML:** `TemplateManagementService` stores thumbnails and managed HTML through `MediaStorageService` when Cloudinary active; delete handles http(s) Cloudinary URLs.
- **Template snapshots:** `TemplateSnapshotService` can store snapshot HTML in Cloudinary (raw) and read snapshot http URLs.
- **Expired invitation prune cleanup:** `InvitationExpiryService` collects known media paths from invitation relations and deletes via `MediaStorageService` before/after DB deletion; local directory cleanup retained as fallback.
- **Gallery dimensions:** `InvitationDataService` handles http(s) image URLs for `getimagesize` with fallback dimensions.

### URL/Display Compatibility

- Models already handling http(s): `Music::url()`, `InvitationGallery::url()`, `InvitationSetting::resolvedMusicUrl()`, `InvitationGiftAccount::qrUrl()`, `Template::thumbnailUrl()`.
- `InvitationDataService::assetUrl()` returns http(s)/absolute paths as-is and falls back to `asset('storage/...')` for legacy relative paths.
- Editor Blade views already handled http(s) image/audio URLs.

### Storage Lifecycle Verification

- **Static code audit:** upload, URL resolve, replace, delete, rollback cleanup paths were wired through `MediaStorageService`.
- **Runtime Cloudinary verification:** not executed in this session (no Cloudinary credentials used/mutated; existing `.env` secrets were not inspected beyond key names).
- **Cloudinary public_id:** current implementation stores secure URLs; deletion parses public_id from Cloudinary URL. This is functional for standard Cloudinary delivery URLs but less robust than storing explicit `public_id/resource_type` columns. Consider adding DB columns in a follow-up migration for stronger lifecycle management.
- **Orphan asset risk:** if DB update fails after Cloudinary upload, some orphan assets may remain. Delete is best-effort and logged.

### Remaining Local Storage Usage

- **Local fallback remains intentional** when Cloudinary is not configured/active (dev/local compatibility).
- **Legacy local media paths** (`storage/...`, `/uploads/templates/...`) are still recognized for display/deletion compatibility.
- **Template source files in repo** (`template/tema1.html` etc., `public/assets/templates/...`) remain local/static and are not user-uploaded media.
- **Vercel ephemeral filesystem:** `api/index.php` and bootstrap use `/tmp` for runtime storage/cache. Persistent user media must not rely on local disk.

## D. Vercel Readiness

### Configuration Files Added/Updated

- `vercel.json` — install/build commands, `vercel-php@0.7.4` function for `api/index.php`, output directory `public`, filesystem/static routing + fallback to API.
- `api/index.php` — sets `/tmp/storage` and `/tmp/bootstrap/cache`, copies bootstrap cache/providers files where possible, forwards to `public/index.php`.
- `bootstrap/app.php` — uses `/tmp/storage` and `/tmp/bootstrap` on Vercel, trusts proxies, preserves CSRF exception for `payment/notification`.

### Runtime / Build / Routes

- **Build command in vercel.json:** `composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist && npm ci` then `npm run build`.
- **Routes:** static build/assets served by filesystem; all other routes fall back to Laravel through `api/index.php`.
- **Trust proxies:** enabled for `*` to support HTTPS/signed URL generation behind Vercel proxy.
- **Storage/cache on serverless:** configured to `/tmp`. Persistent data must be in PostgreSQL/Cloudinary/managed services.

### Serverless Limitations

- **Ephemeral filesystem:** local uploads, sessions, cache, logs, compiled views cannot persist across instances/invocations. Use Cloudinary for media; DB/managed drivers for session/cache.
- **No persistent queue worker:** default recommendation `QUEUE_CONNECTION=sync`. Existing jobs/queue behavior should be reviewed before relying on async workers on Vercel.
- **Scheduler:** Laravel scheduler/cron commands (e.g., preview expiry sweep) require Vercel Cron or external cron trigger. No in-repo Vercel cron configured.
- **Cold starts / PHP runtime:** `vercel-php` is a third-party runtime; behavior, extensions, and limits must be verified in target Vercel project.
- **Long-running tasks:** image optimization, large uploads, and external API calls may hit serverless limits.
- **Local legacy media:** old files in `public/storage` or local uploads are not deployable to Vercel unless migrated to Cloudinary.

### Required Vercel Environment Variables

At minimum:

- `APP_NAME`, `APP_ENV=production`, `APP_KEY`, `APP_DEBUG=false`, `APP_URL`
- `APP_TIMEZONE=Asia/Makassar`
- `DB_CONNECTION=pgsql`, `DATABASE_URL`, `DB_SSLMODE` (as required)
- `CLOUDINARY_URL`, `FILESYSTEM_DISK=cloudinary`
- `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=sync` (or managed queue)
- `LOG_CHANNEL=stderr`/`stack` as appropriate
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`, `MIDTRANS_NOTIFICATION_URL`, `MIDTRANS_PAYMENT_TYPE`, `MIDTRANS_EXPIRY_MINUTES`
- `VITE_APP_NAME` if used by frontend build

## E. GitHub Readiness

### .gitignore

Updated to ignore env variants (`.env.*` except `.env.example`), Vercel artifacts, logs, storage runtime contents, and local upload/template snapshot directories. Review before commit to ensure no required tracked asset is unintentionally ignored.

### .env.example

Rewritten with PostgreSQL/Cloudinary/Vercel-safe placeholders. No secrets included. Includes `DATABASE_URL`, `CLOUDINARY_URL`, `FILESYSTEM_DISK=cloudinary`, serverless-friendly session/cache/queue defaults.

### README.md

Created with project overview, stack, local setup, PostgreSQL/Cloudinary/Vercel configuration, build/test/deploy instructions, and troubleshooting.

### Secrets Audit

- Inspected `.env` key names only; did not read or expose secret values.
- No secrets were written into source files, `.env.example`, README, or this report.
- `.env` remains ignored by `.gitignore`.
- Midtrans server key remains server-side config only; webhook CSRF exemption limited to `payment/notification`.

## F. Regression Testing

| Area | Result | Notes |
|---|---:|---|
| PHP syntax check (touched files) | PASS | `php -l` passed for all modified/created PHP files checked. |
| Laravel Pint (touched files) | PASS | Pint fixed style issues in touched files. |
| Frontend build (`npm run build`) | NOT RUN | `node_modules`/`vite` not available; `npm install` attempt timed out in this session. Build should be run in CI/local with dependencies installed. |
| Laravel automated tests | NOT RUN | No PostgreSQL `undangan_digital_test` environment was provisioned/verified in-session. Running tests against unavailable PG would be invalid. |
| PostgreSQL fresh migration | NOT RUN | Requires accessible PostgreSQL instance/credentials and safe test DB. |
| Cloudinary upload/replace/delete runtime | NOT RUN | Requires Cloudinary credentials and live API; no credentials were used/exposed. |
| Midtrans webhook end-to-end | NOT RUN | Requires Midtrans sandbox credentials and reachable notification endpoint. |
| Google OAuth end-to-end | NOT RUN | Requires configured Google OAuth client and callback URL. |
| Vercel deployment/runtime | NOT RUN | Requires Vercel project, env vars, and deployment; config prepared but not deployed. |
| Existing admin/customer/public flows | NOT VERIFIED | Code-path integration done; runtime regression tests still required. |

## G. Remaining Issues

1. **Composer Cloudinary package not installed.** Direct HTTP Cloudinary integration was implemented instead. Evaluate whether to adopt official package.
2. **No live PostgreSQL migration/test execution.** Fresh migrate/seed and full test suite must be run on PostgreSQL.
3. **No live Cloudinary runtime verification.** Upload/replace/delete/URL behavior needs real Cloudinary credentials.
4. **No Vercel deployment verification.** `vercel-php@0.7.4`, build, routing, /tmp behavior, cold starts, and env loading must be verified in actual Vercel project.
5. **Queue/scheduler on serverless.** Queue defaults to sync recommendation; scheduled expiry command needs Vercel Cron/external scheduler.
6. **Cloudinary deletion robustness.** Deletion parses public_id from URL; storing explicit `public_id`/`resource_type` DB fields would be more reliable.
7. **Legacy local media migration.** Existing local media files are not automatically migrated to Cloudinary. A one-time migration script/command is recommended before Vercel cutover.
8. **`phpunit.xml` changed to PG.** Local/CI environments that previously relied on MySQL test DB must provision PostgreSQL or override env vars.
9. **Case-insensitive search changed to `LOWER(...) LIKE ?`.** Works cross-db but may not use some indexes; acceptable for admin search volumes, can be optimized with PG-specific indexes if needed.
10. **`npm install` timed out; frontend build not verified.** Run `npm ci && npm run build` in a clean environment.

## H. Deployment Instructions

### 1. Prepare External Services

- Create/provision a PostgreSQL database (Neon/Supabase/RDS/etc.). Obtain `DATABASE_URL` or DB host/user/password.
- Create/provision Cloudinary. Obtain `CLOUDINARY_URL`.
- Configure Midtrans Core API keys and notification URL.
- Configure Google OAuth credentials and redirect URI.

### 2. Configure Vercel Project

1. Import the GitHub repository into Vercel.
2. Set environment variables (Production/Preview as needed):
   - `APP_NAME=Undangan Digital`
   - `APP_ENV=production`
   - `APP_KEY=base64:...` (generate with `php artisan key:generate --show`)
   - `APP_DEBUG=false`
   - `APP_URL=https://your-domain`
   - `APP_TIMEZONE=Asia/Makassar`
   - `DB_CONNECTION=pgsql`
   - `DATABASE_URL=<your-postgres-url>`
   - `DB_SSLMODE=require` (provider-dependent)
   - `CLOUDINARY_URL=<your-cloudinary-url>`
   - `FILESYSTEM_DISK=cloudinary`
   - `SESSION_DRIVER=database`
   - `SESSION_SECURE_COOKIE=true`
   - `CACHE_STORE=database`
   - `QUEUE_CONNECTION=sync`
   - `LOG_CHANNEL=stderr`
   - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
   - `GOOGLE_REDIRECT_URI=https://your-domain/auth/google/callback`
   - `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`
   - `MIDTRANS_IS_PRODUCTION=true|false`
   - `MIDTRANS_NOTIFICATION_URL=https://your-domain/payment/notification`
   - `MIDTRANS_PAYMENT_TYPE=qris` (or chosen default)
   - `MIDTRANS_EXPIRY_MINUTES=30`
3. Confirm build settings use repo `vercel.json` (or equivalent).

### 3. Build / Deploy

- Deploy from Vercel. Verify build logs include Composer install and Vite build.
- If Composer is unavailable in Vercel build image, adjust build strategy (prebuild vendor in CI artifact, or custom build image) and document.

### 4. Database Migration

From a trusted machine/CI with production env vars:

```bash
php artisan migrate --force
php artisan db:seed --force # only if required for initial provisioning; review seed idempotency
```

### 5. Post-Deployment Verification

- Check `/up` health endpoint.
- Login as Admin/Customer; verify Google OAuth callback.
- Create/edit invitation, upload gallery/music/cover, verify Cloudinary URLs render.
- Run payment flow in Midtrans sandbox/production as appropriate; confirm webhook updates status only after verified notification.
- Open public `/u/{slug}` and `/u/{slug}?to=Nama+Tamu`.
- Verify RSVP, greetings, digital gifts, and media loading.
- Confirm no secrets in client bundle/logs.

### 6. Operational Follow-up

- Configure Vercel Cron or external cron for `php artisan invitations:expire-previews`.
- Migrate legacy local media to Cloudinary if existing production data contains local paths.
- Run full PHPUnit suite against PostgreSQL in CI.
- Monitor Cloudinary asset cleanup/orphans and consider adding `public_id` columns.
