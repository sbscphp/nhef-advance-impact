# NHEF Nexus

Laravel API backend for the NHEF Nexus platform. This repository currently focuses on **authentication and account management** for admins and customers, with optional API transport encryption, OTP (email/SMS), roles/permissions, audit trails, and notifications.

API base path: `/api` (Laravel default). Versioned routes live under `/api/v1/...`.

---

## Tech stack

| Layer | Choice |
|-------|--------|
| Runtime | PHP 8.2+ |
| Framework | Laravel 12 |
| Auth | Laravel Sanctum (access + refresh bearer tokens) |
| Authorization | Spatie Laravel Permission (`api` guard) |
| Database | MySQL (default) |
| Queue / cache / session | Database drivers by default |
| SMS | Termii (NG), Twilio / Infobip (foreign), stub mode for local |
| Media | Cloudinary (optional disk) |
| Frontend scaffold | React 19 + Inertia 2 + Vite 7 + Tailwind 4 (composer/npm scripts; primary product surface is the JSON API) |

---

## Requirements

- PHP 8.2+ with common Laravel extensions (pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath)
- Composer 2
- Node.js 20+ and npm (for Vite / `composer run setup`)
- MySQL 8+ (or compatible)
- Optional: Redis (cache/queues), queue worker for invite emails and notifications

---

## Quick start

```bash
# Install PHP deps, copy .env if missing, generate key, migrate, build frontend assets
composer run setup

# Configure database (and mail/SMS as needed) in .env, then:
php artisan migrate
php artisan db:seed

# Run API + queue + logs + Vite together
composer run dev
```

Or step by step:

```bash
cp .env.example .env
composer install
php artisan key:generate

# Edit .env — set DB_* at minimum
php artisan migrate
php artisan db:seed

npm install
npm run build   # or: npm run dev
php artisan serve
```

Health check: `GET /up`  
Root: `GET /` → `{ "name": "NHEF Nexus", "status": "ok" }` (name follows `APP_NAME`).

---

## Application domains

| Persona | Prefix | Model | Notes |
|---------|--------|-------|--------|
| **Customer** | `/api/v1/auth/...`, `/api/v1/settings/...`, `/api/v1/notifications/...` | `User` | Signup, email OTP verification, login OTP (if 2FA), forgot password, biometrics setting |
| **Admin** | `/api/v1/admin/...` | `Admin` | Login OTP (if 2FA), forgot password, roles/permissions, admin users + invite links, audit trail, settings |
| **Developer helpers** | `/api/v1/dev/crypto/...` | — | Encrypt/decrypt payloads for a registered API consumer (`X-ClientKey` + `X-Dev-Api-User-Secret`) |
| **API consumers** | Header `X-ClientKey` | `ApiUser` | Per-client AES keys and encryption mode for transport encryption |

Roles (seeded): `Super Admin`, `Admin`, `Customer`. Admin permissions cover roles CRUD, admin users CRUD, and audit trail read.

---

## Auth overview

### Tokens

- Short-lived **access** token (Bearer) for API calls
- Longer-lived **refresh** token, usable only on refresh endpoints
- Refresh tokens are rejected as normal Bearer credentials (`EnsureAccessToken`)
- Optional single-session behaviour: new login can revoke other tokens for the same account (`ADMIN_INVALIDATE_TOKENS_ON_LOGIN` / `CUSTOMER_INVALIDATE_TOKENS_ON_LOGIN`)

Token lifetimes (optional env; defaults in `config/security.php`):

- `ACCESS_TOKEN_MINUTES` (default `60`)
- `REFRESH_TOKEN_DAYS` (default `1`)
- `REFRESH_TOKEN_ROTATION` (default `true`)

### Customer flow

1. `GET /api/v1/auth/registration/options` — signup metadata  
2. `POST /api/v1/auth/signup` → email verification OTP challenge  
3. `POST /api/v1/auth/email/verify-otp` (resend via `email/resend-otp`)  
4. `POST /api/v1/auth/login` → tokens, or OTP challenge if 2FA is on  
5. `POST /api/v1/auth/login/verify-otp` / `login/resend-otp`  
6. Forgot password: `forgot-password` → verify OTP → `reset-password`  
7. `POST /api/v1/auth/refresh`, `POST /api/v1/auth/logout`

### Admin flow

1. `POST /api/v1/admin/auth/login` → tokens or OTP if 2FA is on  
2. OTP verify/resend under `admin/auth/login/...`  
3. Forgot password under `admin/auth/forgot-password...` + `reset-password`  
4. Invited admins get a **set password** email linking to the admin SPA (`ADMIN_FRONTEND_URL`); they cannot log in until `must_reset_password` is cleared  
5. Authenticated: notifications, settings, roles, admin users, audit trails

### OTP & SMS

- OTP validity and throttle windows share `OTP_MINUTES` (keep `APP_TIMEZONE` and `DB_TIMEZONE` aligned — UTC recommended).
- Local default: SMS OTPs use stub code `123456` when `OTP_SMS_DISPATCH_ENABLED=false` (no provider calls).
- When dispatch is enabled, routing uses `SMS_MODE` and `SMS_FOREIGN_PROVIDER` (Termii for Nigeria `234`, Twilio/Infobip for foreign numbers).

### Opaque errors

Login and forgot-password can return generic messages (anti-enumeration). Controlled by `AUTH_OPAQUE_ERRORS` and per-flow toggles — see [Environment variables](#environment-variables).

---

## Seeded local accounts

After `php artisan db:seed` (password for all: `password`):

| Email | Type | 2FA |
|-------|------|-----|
| `nhef-admin-1@yopmail.com` | Super Admin | On |
| `nhef-admin-2@yopmail.com` | Admin | Off |
| `nhef-customer-1@yopmail.com` | Customer | Off |
| `nhef-customer-2@yopmail.com` | Customer | On |

Also seeded: roles/permissions, countries, theme, and an `ApiUser` from `API_USER_SEEDER_*` (default email `api-seed@example.com` if unset).

Optional: `php artisan db:seed --class=OverrideUserSeeder` for encryption-override test emails listed in `config/security.php`.

---

## Useful commands

```bash
composer run setup      # install, .env, key, migrate, npm build
composer run dev        # serve + queue:listen + pail + vite
composer run test       # pint --test + phpunit
composer run lint       # pint

php artisan migrate
php artisan db:seed
php artisan db:seed --class=AdminSeeder
php artisan queue:listen
php artisan config:clear
```

---

## Project structure (high level)

```
app/
  Enums/                 # Roles, permissions, OTP, client types
  Http/Controllers/v1/   # Admin, Customer, Developer
  Http/Middleware/       # Encryption, access-token guard, last-active
  Models/                # User, Admin, ApiUser, Role, Permission, …
  Services/
    Auth/                # Login, OTP, password reset, challenge tokens
    Admin/UserManagement/
    ThirdParty/SMS/      # Termii, Twilio, Infobip
    Settings/, Audit/, Notifications/, Theme/
  Support/               # Password rules, SMS mode, encryption overrides
bootstrap/app.php        # Routes + global API middleware
config/security.php      # Auth, OTP, tokens, encryption
config/services.php      # SMS provider credentials
config/sms-usage.php     # Monthly SMS budget alerts
database/migrations/
database/seeders/
docs/                    # Extra platform docs (e.g. giving identities)
routes/
  api.php                # Requires customer, admin, developer
  admin.php
  customer.php
  developer.php
  web.php
tests/
```

---

## Environment variables

Copy `.env.example` to `.env` and adjust. Below covers the variables that matter for this project (grouped as in `.env.example`).

### Application & URLs

| Variable | Purpose |
|----------|---------|
| `APP_NAME` | Product name in responses, mail from-name defaults, SMS sender defaults |
| `APP_ENV` | Environment (`local`, `staging`, `production`, …). Affects encryption response preview defaults |
| `APP_KEY` | Laravel encryption key (`php artisan key:generate`) |
| `APP_DEBUG` | Detailed errors; keep `false` in production |
| `APP_URL` | Backend base URL |
| `FRONTEND_URL` | Customer SPA base URL (links in customer-facing emails) |
| `FRONTEND_LOGIN_URL` | Optional customer login deep link |
| `ADMIN_FRONTEND_URL` | Admin SPA base URL — used for invite “Set Your Password” links |
| `ADMIN_FRONTEND_SET_PASSWORD_URL` | Optional full override; default `{ADMIN_FRONTEND_URL}/create-new-password` with `?token=...` |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` / `APP_FAKER_LOCALE` | Locale and faker locale |
| `APP_MAINTENANCE_DRIVER` | Maintenance mode storage (`file`, etc.) |
| `APP_TIMEZONE` | App timezone — keep aligned with `DB_TIMEZONE` for OTP expiry |
| `DB_TIMEZONE` | DB session timezone (e.g. `+00:00`) |
| `BCRYPT_ROUNDS` | Password hashing cost |

### Logging

| Variable | Purpose |
|----------|---------|
| `LOG_CHANNEL` | Default channel (e.g. `stack`) |
| `LOG_STACK` | Channels in the stack (e.g. `single`) |
| `LOG_DEPRECATIONS_CHANNEL` | Deprecation log target |
| `LOG_LEVEL` | Minimum level (`debug`, `info`, …) |

### Database

| Variable | Purpose |
|----------|---------|
| `DB_CONNECTION` | Driver (`mysql` default) |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Connection details (uncomment and set for MySQL) |

### Session, queue, cache, filesystem

| Variable | Purpose |
|----------|---------|
| `SESSION_DRIVER` | Session store (`database` default) |
| `SESSION_LIFETIME` | Minutes |
| `SESSION_ENCRYPT` / `SESSION_PATH` / `SESSION_DOMAIN` | Cookie/session options |
| `BROADCAST_CONNECTION` | Broadcasting (`log` for local) |
| `FILESYSTEM_DISK` | Default disk (`local`) |
| `QUEUE_CONNECTION` | Queue driver (`database` — run a worker for invite emails) |
| `CACHE_STORE` | Application cache (`database`) |
| `CACHE_LIMITER_STORE` | **Rate-limit store only.** Prefer `file` or `redis` on MySQL — a database limiter can deadlock under concurrent throttled requests |
| `CACHE_PREFIX` | Optional cache key prefix |
| `MEMCACHED_HOST` | Memcached host if used |
| `REDIS_*` | Redis client/host/password/port if used |

### Mail

| Variable | Purpose |
|----------|---------|
| `MAIL_MAILER` | Transport (`log` writes to log locally; use `smtp`/`ses`/etc. in real envs) |
| `MAIL_SCHEME` / `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` | SMTP (or provider) settings |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Default From header |

### AWS (optional)

| Variable | Purpose |
|----------|---------|
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` / `AWS_USE_PATH_STYLE_ENDPOINT` | S3-compatible storage when using the `s3` disk |

### Vite

| Variable | Purpose |
|----------|---------|
| `VITE_APP_NAME` | Exposed to the frontend build (defaults to `APP_NAME`) |

### Security & auth (`config/security.php`)

| Variable | Purpose |
|----------|---------|
| `AUTH_OPAQUE_ERRORS` | Global override: empty/`null` → use per-flow flags; `true` → force opaque login + forgot-password errors; `false` → force explicit errors |
| `LOGIN_OPAQUE_ERRORS` | Generic login failures (anti-enumeration) when global override is unset |
| `FORGOT_PASSWORD_OPAQUE_ERRORS` | Hide whether an account exists in forgot-password when global override is unset |
| `LOGIN_LOCK_ENABLED` | Account lockout after failed credential attempts (default off) |
| `LOGIN_LOCK_ATTEMPTS` | Failures before lock |
| `LOGIN_LOCK_MINUTES` | Lock duration (default `1440` = 24h) |
| `LOGIN_REVEAL_PERMISSIONS` | Include roles/permissions on admin login payload |
| `ADMIN_INVALIDATE_TOKENS_ON_LOGIN` | Revoke other admin sessions on new login (recommended in production) |
| `CUSTOMER_INVALIDATE_TOKENS_ON_LOGIN` | Same for customers |
| `LAST_ACTIVE_UPDATE_INTERVAL_SECONDS` | Throttle `last_active_at` DB writes (default `300`) |
| `ACCESS_TOKEN_MINUTES` | Sanctum access token TTL (not in `.env.example`; supported in config) |
| `REFRESH_TOKEN_DAYS` | Refresh token TTL |
| `REFRESH_TOKEN_ROTATION` | Rotate refresh token on use |

### OTP

| Variable | Purpose |
|----------|---------|
| `OTP_MINUTES` | OTP / challenge validity **and** throttle window length |
| `OTP_RESEND_TOKEN_MAX_MINUTES` | How long an old `challenge_token` can still be used on resend endpoints |
| `OTP_SEND_MAX_PER_WINDOW` | Max OTP send/resend requests per window |
| `OTP_VERIFY_MAX_PER_WINDOW` | Max OTP verify attempts per window |
| `OTP_FLOW_DEBUG` | Log OTP HTTP flow fingerprints (no passwords, codes, or full tokens) |
| `OTP_SMS_STUB_CODE` | Fixed SMS OTP while dispatch is disabled (default `123456`) |
| `OTP_SMS_DISPATCH_ENABLED` | `false` = stub SMS OTP; `true` = call providers per `SMS_MODE` |

### SMS routing

| Variable | Purpose |
|----------|---------|
| `SMS_MODE` | `stub` (no send), `log` (log payload), or `live` (real providers) |
| `SMS_FOREIGN_PROVIDER` | Foreign-number provider when live (`twilio` or Infobip, depending on wiring) |

Nigeria (`234`) uses **Termii**; foreign numbers use the configured foreign provider.

### API encryption

| Variable | Purpose |
|----------|---------|
| `OVERRIDE_USERS` | When `true`, configured override emails get **plaintext** responses only **after** login (Bearer). Login itself stays encrypted when middleware is on |
| `API_ENCRYPTION_MIDDLEWARE_ENABLED` | Master switch for request/response AES encryption (`X-ClientKey` → `ApiUser`) |
| `API_ENCRYPTION_DEFAULT_MODE` | Default for new consumers: `both` \| `request_only` \| `response_only` |
| `API_ENCRYPTION_RESPONSE_PREVIEW` | In non-production, encrypted responses may include a `preview` of decoded JSON (never relied on in production) |

Developer crypto helpers (`/api/v1/dev/crypto/*`) need `X-Dev-Api-User-Secret` (from registration secret) and `X-ClientKey`.

### API consumer registration & seeder

| Variable | Purpose |
|----------|---------|
| `API_USER_DEV_REGISTRATION_ENABLED` | Allow developer registration of API consumers (when that route is enabled) |
| `API_USER_DEV_REGISTRATION_SECRET` | Shared secret header for dev registration / crypto helpers |
| `API_USER_SEEDER_EMAIL` | Email for `ApiUserSeeder` |
| `API_USER_SEEDER_NAME` | Display name for seeded consumer |
| `API_USER_SEEDER_ENCRYPTION_MODE` | `both` \| `request_only` \| `response_only` |
| `API_USER_SEEDER_IS_ACTIVE` | Whether the seeded consumer is active |

### Cloudinary

| Variable | Purpose |
|----------|---------|
| `CLOUDINARY_API_KEY` / `CLOUDINARY_API_SECRET` / `CLOUDINARY_CLOUD_NAME` / `CLOUDINARY_URL` | Cloudinary disk / uploads (`cloudinary` filesystem disk) |

### Infobip (SMS + usage alerts)

| Variable | Purpose |
|----------|---------|
| `INFOBIP_BASE_URL` | API base URL |
| `INFOBIP_SECRET_KEY` / `INFOBIP_API_KEY` | Credentials |
| `INFOBIP_SENDER` | Sender ID (defaults toward `APP_NAME`) |
| `INFOBIP_TIMEOUT` | HTTP timeout (seconds) |
| `INFOBIP_MONTHLY_BUDGET` / `INFOBIP_BALANCE_CURRENCY` | Budget tracking |
| `INFOBIP_WARN_AT` | Comma-separated % thresholds (e.g. `50,75,90`) |
| `INFOBIP_ALERT_EMAILS` | Recipients for budget warnings |

### Termii (Nigeria SMS + usage alerts)

| Variable | Purpose |
|----------|---------|
| `TERMII_BASE_URL` | API base URL |
| `TERMII_API_KEY` | API key |
| `TERMII_MONTHLY_BUDGET_NGN` | Monthly budget in NGN |
| `TERMII_SENDER` | Sender ID |
| `TERMII_TIMEOUT` | HTTP timeout |
| `TERMII_WARN_AT` | Warning thresholds (%) |
| `TERMII_ALERT_EMAILS` | Alert recipients |

### Twilio (SMS + usage alerts)

| Variable | Purpose |
|----------|---------|
| `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN` | Credentials |
| `TWILIO_FROM` | E.164 sender (e.g. `+15551234567`) or Messaging Service SID (`MG...`) |
| `TWILIO_TIMEOUT` | HTTP timeout |
| `TWILIO_MONTHLY_BUDGET` / `TWILIO_BALANCE_CURRENCY` | Budget tracking |
| `TWILIO_WARN_AT` | Warning thresholds (%) |
| `TWILIO_ALERT_EMAILS` | Alert recipients |

---

## Local development tips

1. **Mail:** Keep `MAIL_MAILER=log` and read OTP/invite content from `storage/logs/laravel.log` (or Mailpit/SMTP if you prefer).
2. **SMS OTP:** Leave `OTP_SMS_DISPATCH_ENABLED=false` and use `OTP_SMS_STUB_CODE` (default `123456`).
3. **Encryption:** Leave `API_ENCRYPTION_MIDDLEWARE_ENABLED=false` until clients send `X-ClientKey` and encrypted bodies. Use `/api/v1/dev/crypto/*` to build payloads.
4. **Rate limits:** Set `CACHE_LIMITER_STORE` to `file` or `redis` if you hit MySQL deadlocks under load.
5. **Queues:** Admin invite emails are queued — run `php artisan queue:listen` or `composer run dev`.
6. **Opaque errors:** Set `LOGIN_OPAQUE_ERRORS=false` and/or `FORGOT_PASSWORD_OPAQUE_ERRORS=false` (or `AUTH_OPAQUE_ERRORS=false`) while debugging auth locally.

---

## Testing

```bash
composer run test
# or
php artisan test
```

---

## Related docs

- [`docs/GIVING_IDENTITIES.md`](docs/GIVING_IDENTITIES.md) — donor/giving identity concepts for the wider platform (not all of that surface is wired in the current auth-focused API routes).

---

## License

MIT (see `composer.json`).
