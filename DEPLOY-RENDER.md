# Deploy Kusina for free on Render + TiDB Cloud

A **$0 demo/portfolio setup**: Render runs the app (one free container with the website and the live-updates server) and TiDB Cloud provides a free MySQL-compatible database. All the config is in `render.yaml`.

> ## Read this first: what "free" costs you
> | Limitation | What it means |
> |---|---|
> | **The site sleeps** after ~15 minutes with no visitors | The next visitor waits about a minute for it to wake up. Live updates stop while it sleeps. |
> | **No permanent disk** | Photos uploaded in Admin (menu photos, Gallery) **disappear** whenever the service restarts, redeploys or wakes from sleep. Your data in the database is safe. |
> | Small instance (reportedly 512 MB RAM, a fraction of a CPU) | Fine for a demo; slow under real load. |
> | 750 free hours/month | Enough for one service running all month (a 31-day month is 744 h), then it stops until next month. |
>
> **This is for demos and testing, not for taking real orders.** For a real restaurant use a paid always-on host (see `DEPLOY.md`). Render's free limits can change, so check [Render's free tier docs](https://render.com/docs/free).
>
> **Not yet run:** this setup (and the Docker image) was written and syntax-checked but I could not build or run it, and it has not been tested against TiDB. Expect to troubleshoot on the first deploy (section 6).

**Time needed:** about 30–45 minutes. **Cost:** $0 (no card needed for Render's free web service or TiDB's free tier, though either may ask for verification).

---

## 1. Put the code on GitHub
Your repo is already at `github.com/ayse0dev/Kusina-Ordering-App`. Make sure the latest changes (including `Dockerfile`, `docker/`, `render.yaml`) are pushed to the `main` branch.

## 2. Create the free database (TiDB Cloud)
1. Sign up at <https://tidbcloud.com> and create a **Starter** (free) cluster. Pick a region near Render's Oregon (e.g. AWS **us-west-2**).
2. Open the cluster → **Connect**. Choose a **public endpoint**, and click to generate the password. Write down:
   - **Host** (like `gateway01.us-west-2.prod.aws.tidbcloud.com`)
   - **Port** `4000`
   - **User** (looks like `abc123xyz.root`: the whole thing, including the prefix)
   - **Password**
3. Open the **SQL Editor** and run: `CREATE DATABASE kusina;`
4. TiDB requires an encrypted connection: that's already handled (`MYSQL_ATTR_SSL_CA` in `render.yaml`).

## 3. Generate your app key
On your computer, in the project folder:
```powershell
php artisan key:generate --show
```
Copy the whole output (it starts with `base64:`).

## 4. Create the service on Render
1. Sign up at <https://render.com> (you can use your GitHub login) and authorize access to your repository.
2. **New → Blueprint** → pick the `Kusina-Ordering-App` repo. Render reads `render.yaml` and shows one service, **kusina**, on the **Free** plan.
3. Fill in the prompts:

| Prompt | Value |
|---|---|
| `APP_KEY` | the `base64:...` value from step 3 |
| `DB_HOST` | TiDB host |
| `DB_DATABASE` | the database you created in TiDB (for example `kusina` or `kusina2`) |
| `DB_USERNAME` | TiDB user (with the prefix) |
| `DB_PASSWORD` | TiDB password |

4. Click **Apply**. The first build takes roughly 5–12 minutes (it installs PHP and JS packages and builds the site).

On its first start the app also loads the starter menu and the two-floor restaurant plan (it never overwrites existing data). Edit both afterwards in the Admin area.

## 5. Create your admin login
When the service shows **Live**, open it: Render dashboard → **kusina** → **Shell** tab (the free plan may not include a shell; if not, use the alternative below), then run:
```bash
php artisan kusina:admin you@example.com --name="Your Name"
```
It prints a generated password. Sign in at `https://<your-service>.onrender.com/login` and change it.

*If your plan has no Shell:* run the same command from your own computer against the cloud database. Put the TiDB values in a temporary file named `.env.cloud` in the project folder (never commit it), then:
```powershell
$env:DB_CONNECTION="mysql"; $env:DB_HOST="<host>"; $env:DB_PORT="4000"; $env:DB_DATABASE="kusina"; $env:DB_USERNAME="<user>"; $env:DB_PASSWORD="<password>"; $env:MYSQL_ATTR_SSL_CA="C:\path\to\cacert.pem"
php artisan migrate --force
php artisan kusina:admin you@example.com
```
(`cacert.pem` is a CA bundle, available from <https://curl.se/ca/cacert.pem>.) Close the terminal afterwards so the password isn't left in your environment.

## 5b. Receipt emails (optional)
By default the demo does **not** send real emails: receipts are only written to the app log (`MAIL_MAILER=log`). To send them for real, open Render dashboard → **kusina** → **Environment** and change/add:

| Key | Example value |
|---|---|
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | your provider's SMTP host (Brevo: `smtp-relay.brevo.com`, Gmail: `smtp.gmail.com`) |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | your SMTP login |
| `MAIL_PASSWORD` | your SMTP password or app password (keep it private) |
| `MAIL_FROM_ADDRESS` | an address your provider allows you to send from |

Each receipt email carries the PDF receipt. If mail fails, bookings and orders still go through (the error is only logged).

## 6. Check it works
1. Open the site URL: the home page loads (the first visit after sleep takes ~1 minute).
2. Log in to `/login`; the Orders board shows **live**.
3. In a second browser, place an order: it appears instantly on the board (this proves the live-updates path).
4. Reserve a table: the 3D building loads, and the time picker shows Edmonton time.
5. On the reservation page, press **Print receipt**: a PDF downloads.
6. Place a dine-in order with your reservation code (ordering opens 15 minutes before the booking). Open the tracking link: the kitchen shows the cooking bar. In the staff board, move the order to **Preparing**, then **Ready**: the waiter serves the table and the order completes.

**If something fails:** Render dashboard → **Logs**.
| Symptom | Likely cause |
|---|---|
| `FATAL: required environment variable ... is not set` | A prompt value was left empty (Environment tab). |
| Database connection error | Wrong host/user (the user must include the prefix), or the `kusina` database wasn't created. |
| Migration error mentioning foreign keys/indexes | TiDB compatibility quirk: copy the error to me and I'll adjust the migration. |
| Build killed / out of memory | Retry the deploy; the free build machine is small. |
| 502 / keeps restarting | Open Logs; if it says "out of memory", the free instance is too small for your traffic. |

## 7. Keep it awake (optional, but helpful for a demo)
Create a free monitor at <https://uptimerobot.com> that requests `https://<your-service>.onrender.com/up` every 5 minutes. That keeps the service from sleeping, so visitors don't wait, but it uses your free hours continuously (about 744 of the 750 hours in a 31-day month).

## 8. Updating
Push to `main` on GitHub; Render redeploys automatically. Remember: each redeploy also wipes uploaded photos.

## 9. Making photos permanent later (optional)
The simplest fixes: move to a paid plan with a persistent disk, or store uploads in an S3-compatible bucket (Cloudflare R2 / Backblaze B2 both have free tiers). Ask me and I'll add S3 storage support.
