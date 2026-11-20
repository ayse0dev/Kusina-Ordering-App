# Deploying Kusina

Kusina needs PHP, a MariaDB database, a long-running WebSocket server and a writable disk for photo uploads, so it **can't run on Vercel** (static/serverless only). This repo ships a ready-to-run Docker setup for any Linux server (a small VPS is plenty: 2 GB RAM, 1–2 vCPU, ~$6–12/month at DigitalOcean, Hetzner, Linode, Vultr…).

```
Internet ──► Caddy (HTTPS, auto certificates)
                └─► web    nginx + php-fpm   (the site; /app is forwarded to reverb)
                      ├─► db      MariaDB 11.8 (not exposed to the internet)
                      └─► reverb  WebSockets (live orders, tables, tracking)
                queue  background worker
```

> **Not yet tested:** the Docker image was written and checked (shell syntax, compose file, config) but **not built or run**: Docker isn't installed on the machine it was authored on. Expect to fix small things on the first build, and follow section 7 (smoke test) before announcing the site.

## 1. Prerequisites
- A Linux server (Ubuntu 24.04 LTS recommended) and a **domain** whose DNS `A` record points at the server's IP.
- Docker Engine + the Compose plugin: <https://docs.docker.com/engine/install/ubuntu/>
- Open only ports **22, 80, 443** (`ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw enable`). The database and WebSocket server are never published to the internet.

## 2. Get the code and configure
```bash
git clone https://github.com/<you>/Kusina-Ordering-App.git && cd Kusina-Ordering-App
cp .env.production.example .env.production
nano .env.production
```
Fill in at least: `DOMAIN`, `APP_URL`, `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REVERB_APP_ID/KEY/SECRET`, and your restaurant details.
- `APP_KEY`: `echo "base64:$(openssl rand -base64 32)"`
- passwords / Reverb key and secret: `openssl rand -hex 24` (use letters and digits only: no `$`, `#` or quotes)
- `chmod 600 .env.production`

## 3. First start
```bash
docker compose --env-file .env.production up -d --build
docker compose --env-file .env.production logs -f web     # wait for "Running database migrations..." then it settles
```
`--env-file .env.production` is important: it's how Compose reads `DOMAIN` and the passwords.

Create your admin login (no demo data is added):
```bash
docker compose --env-file .env.production exec web php artisan kusina:admin you@example.com --name="Your Name"
```
It prints a generated password. Sign in at `https://YOUR-DOMAIN/login`, then **change the password**. Add your menu in Admin → Menu, floors in Admin → Floor plan, photos in Admin → Gallery.

*(Optional sample menu/floors: `docker compose --env-file .env.production exec web php artisan db:seed --force`. This also creates a demo admin and prints its password.)*

## 4. Connect Stripe and couriers
- **Stripe:** set `STRIPE_SECRET_KEY`; in the Stripe dashboard add a webhook `https://YOUR-DOMAIN/webhooks/stripe` for `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired` and put its signing secret in `STRIPE_WEBHOOK_SECRET`.
- **Delivery:** see `SECURITY.md` ("Delivery couriers"). Uber Direct webhook URL: `https://YOUR-DOMAIN/webhooks/courier/uber`.
- After editing `.env.production`: `docker compose --env-file .env.production up -d` (add `--build` if you changed `DOMAIN` or the `REVERB_*` keys, because those are baked into the site's JavaScript).
- Set real SMTP details so gift vouchers are emailed (`MAIL_*`).

## 5. Updating the site
```bash
git pull
docker compose --env-file .env.production up -d --build
```
Migrations run automatically when the `web` container starts. Old images can be cleaned with `docker image prune -f`.

## 6. Backups (do this before you have real orders)
```bash
# Database dump (put this in a daily cron job, and copy the file OFF the server)
docker compose --env-file .env.production exec -T db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction kusina' | gzip > kusina-$(date +%F).sql.gz

# Uploaded photos live in the "storage" volume
docker run --rm -v kusina-ordering-app_storage:/s -v "$PWD":/b alpine tar czf /b/storage-$(date +%F).tgz -C /s app/public
```
Restore a dump: `gunzip -c kusina-DATE.sql.gz | docker compose --env-file .env.production exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" kusina'`.
Keep the `caddy-data` volume too (it holds your HTTPS certificates). Test a restore at least once.

## 7. Smoke test after the first deploy
1. `https://YOUR-DOMAIN` loads with a padlock, and the menu appears.
2. Log in at `/login`. Admin → Orders shows "live" and, in a second browser, place a test order: it should appear on the board **instantly** (this proves the WebSocket path through `/app`).
3. Reserve a table: the 3D building loads and colours update.
4. Upload a photo in Admin → Gallery and check it displays (proves storage + the `/storage` route).
5. Stripe test-mode payment: the order moves to paid via the webhook.
6. `docker compose --env-file .env.production ps` shows `web` as `healthy`.

## 8. Troubleshooting
| Symptom | Check |
|---|---|
| Container exits with `FATAL: required environment variable ...` | A value is empty in `.env.production`. |
| 502 from Caddy | `docker compose ... logs web` (migrations failing? bad DB password?). |
| Site works but nothing updates live | `docker compose ... logs reverb`; confirm `REVERB_APP_KEY` didn't change since the last `--build`. |
| Links/redirects use `http://` | `APP_URL` must start with `https://` and `TRUSTED_PROXIES=*`. |
| Everything logs out / "419 expired" | `SESSION_SECURE_COOKIE=true` requires HTTPS; check `APP_URL`. |
| Emails fail | Use SMTP or an API mailer, never `sendmail` (blocked in the web container). |

Logs: `docker compose --env-file .env.production logs -f --tail=100 web queue reverb`.

## 9. Other hosts
- **Railway / Render / Fly.io / any container platform:** build the same `Dockerfile` and run it as three services with `CONTAINER_ROLE=web`, `reverb` and `queue` (`RUN_MIGRATIONS=true` on `web` only), plus a managed MariaDB/MySQL database and a persistent disk mounted at `/var/www/html/storage`. Set `REVERB_UPSTREAM=<reverb service host>:8080` on `web`, and pass the `VITE_REVERB_*` values as build arguments (see `docker-compose.yml`). The platform provides HTTPS, so you don't need Caddy.
- **Laravel Forge / Ploi on a VPS (no Docker):** install PHP 8.4 with `gd intl pdo_mysql zip bcmath`, point the site at `public/`, use `docker/nginx/default.conf.template` as a reference for the security rules, run `php artisan reverb:start` and `php artisan queue:work` as daemons, and apply the hardening list in `SECURITY.md`.

## Security notes
- The web container can't run commands: `exec`, `shell_exec`, `system`, `proc_open`, `popen` are disabled for web requests, only `index.php` may execute, and `/storage` (uploads) never executes code.
- Secrets live only in `.env.production` (git-ignored). If it, or the server, was ever exposed, rotate every value in it.
- Keep the OS and images patched: `apt upgrade` monthly and re-run the update step in section 5 (it pulls fresh PHP/MariaDB/Caddy base images when you add `--pull`).
