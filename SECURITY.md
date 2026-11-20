# Kusina: security & deployment notes

## What the app already does
- **Uploads can never run as code.** Dish photos go through `App\Support\ImageSanitizer`: the file is decoded with GD and re-encoded as a new WebP with a random name. Anything else (e.g. a `.php` file renamed `.png`) is rejected.
- **Prices are never trusted from the browser.** The cart stores only IDs and quantities; totals are recomputed from the database. Orders keep price snapshots.
- **Stripe webhook** requires a valid `Stripe-Signature` HMAC (5-minute timestamp window) and a payment amount that covers what is due. Orders and gift vouchers are activated *only* by this webhook, never by the browser redirect. Abandoned checkouts (`checkout.session.expired`) cancel the order and give back any voucher balance.
- **Vouchers are server-validated.** Codes are re-checked under a row lock at checkout (expiry, usage limits, minimum spend, balance), so they cannot be double-spent. Gift codes are random, only revealed after payment is confirmed, and balance checks are rate-limited.
- **Delivery is server-authoritative.** The courier fee and ETA come from a server-side quote kept in the session (never from the browser); quotes in a foreign currency are refused, and if a courier can't quote we fall back to the flat fee. Courier webhooks are verified (Uber: HMAC-SHA256 of the raw body; Grab: shared secret token), are matched on provider + delivery id, can only move a delivery forward, and tracking links are accepted only if they are plain `https://` URLs.
- **No public registration.** `users.role` is not mass-assignable. Admin = menu/floor/orders/reservations, staff = orders/reservations.
- **Double-booking is prevented** with a row lock on the table inside a transaction + overlap check.
- CSRF on all forms, Eloquent bindings only (no raw SQL), rate limits on cart, checkout, reservations, tracking and login, security headers + CSP on every response.

## Production checklist
1. `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`, strong DB password, HTTPS only (set `SESSION_SECURE_COOKIE=true`).
2. Web root = `public/` **only**. Never expose the project root, `.env`, `storage/` or `vendor/`.
3. Block PHP execution in upload/storage paths. Nginx:
   ```nginx
   location ^~ /storage/ { location ~ \.php$ { return 403; } }
   location ~ /\.(?!well-known) { deny all; }
   ```
4. Harden `php.ini` (FPM/Apache SAPI):
   ```ini
   expose_php = Off
   display_errors = Off
   allow_url_include = Off
   disable_functions = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,show_source,highlight_file,dl
   open_basedir = /var/www/kusina:/tmp
   session.cookie_httponly = 1
   session.use_strict_mode = 1
   ```
   (The local dev `php.ini` leaves `proc_open`/`popen` enabled because Composer and `artisan serve` need them.)
5. Run `php artisan reverb:start` under a process manager (supervisor/systemd) behind a TLS proxy (`wss://`), and set `REVERB_*`/`VITE_REVERB_*` for the public host, then `npm run build`.
6. Add `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET` to `.env`; in the Stripe dashboard add a webhook endpoint `https://YOUR-DOMAIN/webhooks/stripe` for `checkout.session.completed`, `checkout.session.async_payment_succeeded` and `checkout.session.expired`. For local testing: `stripe listen --forward-to 127.0.0.1:8000/webhooks/stripe`.
7. Set `KUSINA_ADDRESS`, `KUSINA_PHONE` and `KUSINA_EMAIL` (shown on the Contact page), configure real mail (`MAIL_*`) so gift vouchers are emailed, and confirm GST (`KUSINA_TAX_RATE`) and Alberta gift-card rules with your accountant.
8. Keep dependencies patched: `composer audit && npm audit` regularly, and update PHP/MariaDB to supported releases.
9. Change the seeded admin password after first login, and rotate any credentials that were ever exposed.

## Delivery couriers (setup)
- `KUSINA_COURIER=manual` works out of the box (flat fee; staff assign a driver on the order board).
- **Uber Direct (Canada):** create a Direct account, then set `UBER_DIRECT_*`, `KUSINA_PICKUP_ADDRESS`/`_PHONE` and register `https://YOUR-DOMAIN/webhooks/courier/uber` for delivery status events (signing key = `UBER_DIRECT_SIGNING_KEY`). Test in Uber's sandbox first.
- **GrabExpress:** only serves Southeast Asia. Set `GRAB_*` and `KUSINA_PICKUP_LAT/LNG`, and register `https://YOUR-DOMAIN/webhooks/courier/grab?token=<GRAB_WEBHOOK_TOKEN>`. Grab quotes in local currencies, so with `CAD` prices it will correctly fall back to the flat fee.
- The courier adapters follow each provider's published API but have **not** been exercised against live accounts. Verify quotes, bookings, cancellations and webhooks in the provider sandbox before taking real orders.
- Nominatim (OpenStreetMap) is used for the delivery-radius check; for heavy volume switch to a paid geocoder or set `KUSINA_GEOCODER=none`.

## Deployment
A ready-to-run Docker setup (Caddy HTTPS, nginx + php-fpm, MariaDB, Reverb, queue worker) is in `DEPLOY.md`. Its web container disables process-spawning functions, only executes `index.php`, and never executes anything under `/storage`.
