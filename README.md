# INVITE — Digital Wedding Invitation Platform

INVITE is a Laravel-based digital wedding invitation platform with separate Admin and Customer workflows, template-driven invitation rendering, Midtrans Core API payments, Google OAuth authentication, and public guest-facing invitation pages.

This repository has been prepared for a PostgreSQL + Cloudinary + Vercel deployment model while preserving existing application behavior.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2+
- **Database:** PostgreSQL (primary)
- **Media storage:** Cloudinary (primary for user/app-managed media)
- **Frontend:** Blade, vanilla JavaScript, Tailwind CSS v4, Vite
- **Auth:** Laravel session auth + Google OAuth (Socialite)
- **Payments:** Midtrans Core API + webhook verification
- **Hosting target:** Vercel (serverless PHP runtime)

## Key Features

- Admin: dashboard, customer management, invitation management, template management, music management, transactions, vouchers, settings
- Customer: dashboard, invitation creation/editing, template selection, preview, activation/payment, guest management, RSVP, greetings, digital gifts, gallery, music, events, stories, family information
- Public: `/u/{slug}` invitation pages, guest personalization (`?to=`), RSVP, greetings, digital gifts, media, OG metadata
- Payments: Midtrans Core API charge, webhook signature verification, amount verification, idempotent processing, activation only after verified payment

## Local Installation

### Prerequisites

- PHP 8.2+ with extensions required by Laravel and PostgreSQL (`pdo_pgsql`, `pgsql`)
- Composer 2.x
- Node.js 20+ and npm
- PostgreSQL 14+ (or compatible managed PostgreSQL)

### Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` (see below), then:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev
php artisan serve
```

For the all-in-one local dev process (serve, queue listener, pail, Vite):

```bash
composer dev
```

## Environment Configuration

Copy `.env.example` to `.env` and set values for your environment. **Never commit `.env` or secrets.**

### Core

- `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`
- `APP_TIMEZONE` (default `Asia/Makassar`)

### PostgreSQL

Primary database is PostgreSQL.

- `DB_CONNECTION=pgsql`
- `DATABASE_URL=postgres://...` (recommended for managed/Vercel environments)
- Or discrete vars: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE`

For local development, create a PostgreSQL database and run migrations:

```bash
php artisan migrate --seed
```

### Cloudinary

Cloudinary is the primary media storage for user/app-managed uploads.

- `CLOUDINARY_URL=cloudinary://<key>:<secret>@<cloud_name>`
- `FILESYSTEM_DISK=cloudinary`

The application stores Cloudinary secure URLs in media fields and supports upload/delete/URL resolution through `App\Services\Storage\MediaStorageService`. Legacy local `/storage/...` paths remain resolvable for compatibility, but production should use Cloudinary.

### Google OAuth

- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI=${APP_URL}/auth/google/callback`

Configure the same redirect URI in Google Cloud Console.

### Midtrans Core API

- `MIDTRANS_SERVER_KEY` (server-side only)
- `MIDTRANS_CLIENT_KEY`
- `MIDTRANS_IS_PRODUCTION=true|false`
- `MIDTRANS_NOTIFICATION_URL=${APP_URL}/payment/notification`
- `MIDTRANS_PAYMENT_TYPE`
- `MIDTRANS_EXPIRY_MINUTES`

**Security:** The server key must never be exposed to browser/Vite/frontend code. Webhook activation is performed only after signature, order/amount, and idempotency verification.

### Sessions / Cache / Queue

For serverless/Vercel, avoid persistent local file assumptions:

- `SESSION_DRIVER=database` (recommended with external PostgreSQL) or a managed session store
- `CACHE_STORE=database` (recommended) or a managed cache
- `QUEUE_CONNECTION=sync` (safe default without workers) or a managed queue
- `FILESYSTEM_DISK=cloudinary`

## Database Migration

The app is configured for PostgreSQL by default.

```bash
php artisan migrate --seed
```

Notes:

- Migrations have been audited/fixed for PostgreSQL compatibility where identified (including active-expiry backfill).
- `phpunit.xml` defaults to PostgreSQL `undangan_digital_test` for tests.
- Do not run destructive operations against production data without explicit approval.

## Frontend Build

```bash
npm install
npm run build
```

Vite output is generated in `public/build`.

## Running Tests

Create the PostgreSQL test database configured in `phpunit.xml` (`undangan_digital_test` by default), then:

```bash
php artisan test
```

Focused examples:

```bash
php artisan test --testsuite=Feature
php artisan test --filter=SecurityTest
```

## Vercel Deployment

This repository includes Vercel preparation files:

- `vercel.json` — build/install config, PHP serverless function, routing
- `api/index.php` — serverless entrypoint, `/tmp` storage/bootstrap cache wiring

### Required Vercel Environment Variables

Set these in Vercel Project Settings (values only, no secrets in repo):

- `APP_NAME`, `APP_ENV=production`, `APP_KEY`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
- `APP_TIMEZONE=Asia/Makassar`
- `DB_CONNECTION=pgsql`
- `DATABASE_URL` (managed PostgreSQL connection string)
- `DB_SSLMODE=require` (or as required by provider)
- `CLOUDINARY_URL`
- `FILESYSTEM_DISK=cloudinary`
- `SESSION_DRIVER=database` (or managed alternative)
- `SESSION_SECURE_COOKIE=true`
- `CACHE_STORE=database` (or managed alternative)
- `QUEUE_CONNECTION=sync` (or managed queue)
- `LOG_CHANNEL=stderr`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION`, `MIDTRANS_NOTIFICATION_URL`, `MIDTRANS_PAYMENT_TYPE`, `MIDTRANS_EXPIRY_MINUTES`

### Deployment Steps

1. Push repository to GitHub (without secrets).
2. Import project in Vercel.
3. Configure environment variables above.
4. Ensure build can run `composer install` and `npm ci && npm run build` in Vercel environment.
5. Deploy.
6. Run migrations against the production PostgreSQL database from a trusted environment/CI with access to production env vars:

   ```bash
   php artisan migrate --force
   ```

7. Configure Midtrans notification URL to `https://your-domain/payment/notification`.
8. Configure Google OAuth redirect URI to `https://your-domain/auth/google/callback`.
9. Verify public invitation pages, login/OAuth, admin/customer flows, uploads, and payment webhook.

### Serverless Limitations / Notes

- **Filesystem is ephemeral.** Do not rely on local persistent storage. User/app media should go to Cloudinary; sessions/cache should use database or managed services.
- **No persistent queue worker.** `QUEUE_CONNECTION=sync` is the safest default unless using a managed queue/worker outside Vercel.
- **Scheduler/cron.** Vercel cron or external cron is required for scheduled commands. `invitation:expire-previews` should be scheduled externally if needed.
- **Cold starts / execution limits.** Keep requests efficient; large media processing and long jobs should be offloaded.
- **Bootstrap/cache on /tmp.** `api/index.php` and `bootstrap/app.php` configure `/tmp` storage/bootstrap cache for Vercel runtime.
- **Runtime verification.** The included `vercel-php@0.7.4` runtime configuration should be validated in your Vercel account/environment before production cutover.

## Cloudinary Notes

- Uploads are handled through `App\Services\Storage\MediaStorageService`.
- Stored media values may be Cloudinary secure URLs (new) or legacy local relative paths.
- URL resolvers in models/services/views handle both `http(s)` URLs and legacy local paths.
- Replacing/deleting media attempts Cloudinary deletion for Cloudinary URLs; local legacy paths are deleted from local public disk when applicable.

## Troubleshooting

- **DB connection errors:** verify `DATABASE_URL`/DB vars, SSL mode, and PostgreSQL reachability from Vercel.
- **Uploads fail on Vercel:** confirm `CLOUDINARY_URL` and `FILESYSTEM_DISK=cloudinary`.
- **500 on Vercel boot:** check function logs for missing APP_KEY, DB connection, writable `/tmp`, or missing vendor/build artifacts.
- **Sessions/logins not persisting:** use database/managed session driver; avoid file sessions on serverless.
- **Midtrans webhook not activating:** verify notification URL, signature key, amount match, idempotency, and server logs.
- **Google OAuth redirect mismatch:** ensure exact redirect URI matches Google Console and `GOOGLE_REDIRECT_URI`.
- **Legacy local media missing:** pre-Cloudinary local files are not available on Vercel unless migrated to Cloudinary.

## Security

- Do not commit `.env` or credentials.
- Keep Midtrans server key server-side only.
- Validate uploads server-side; enforce ownership/policy checks.
- Payment activation is webhook-verified and idempotent.

## License

Proprietary/internal project unless otherwise specified.
