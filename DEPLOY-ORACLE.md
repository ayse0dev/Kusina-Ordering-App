# Deploy Kusina on Oracle Cloud "Always Free" ($0)

Oracle's Always Free tier gives you a real, always-on Linux server at no cost, which is what this app needs (PHP + database + WebSockets + uploads). This guide takes you from nothing to a live HTTPS site. It uses the Docker setup described in `DEPLOY.md`.

> **Be realistic about "free":** limits and Oracle's UI change. Mid-2026 reports say the free ARM allowance was reduced to about **2 CPUs / 12 GB RAM** (older pages still say 4 / 24). Check [Oracle's Always Free page](https://docs.oracle.com/iaas/Content/FreeTier/resourceref.htm) when you sign up. Also read section 9: free instances can be reclaimed, so **off-server backups are not optional**.
>
> The Docker setup has not been built or run by me yet. Expect to troubleshoot small things on the first build (`DEPLOY.md` section 8 helps).

**Time needed:** about 60–90 minutes. **You'll need:** a credit/debit card (Oracle verifies you; Always Free resources aren't charged), a phone for verification, and ideally a domain name.

---

## 1. Create the Oracle account
1. Go to <https://www.oracle.com/cloud/free/> and click **Start for free**.
2. **Home region: choose carefully, it can't be changed later.** For Alberta pick **Canada Southeast (Toronto)** or **Montreal**.
3. Verify the card. A small temporary hold may appear and then disappears. You stay on the free tier unless you upgrade.

## 2. Create the server (the "instance")
In the Oracle Console: **☰ menu → Compute → Instances → Create instance**.
- **Name:** `kusina`
- **Image:** *Canonical Ubuntu 24.04* (the ARM/aarch64 build is selected automatically with the shape below).
- **Shape:** *Change shape → Ampere → **VM.Standard.A1.Flex***. Set **2 OCPU and 12 GB** memory (stay at or under what the page marks as "Always Free-eligible"; going over can get instances shut down).
- **Networking:** use the default VCN/subnet and make sure **Assign a public IPv4 address** is on.
- **SSH keys:** choose *Generate a key pair* and **download both keys**. Keep the private key safe: it is your only way in.
- **Boot volume:** 50–100 GB is plenty.
- Click **Create**.

**"Out of capacity for shape VM.Standard.A1.Flex"** is common. Try again later (early morning works best) or in another availability domain. If you can't get an ARM machine, the smaller AMD "Micro" shape (1 GB RAM) is too small for this stack.

When it shows **Running**, copy the **Public IP address**.

## 3. Open the firewall (Oracle blocks traffic in TWO places)
**a) Oracle's network rules (VCN):** instance page → click the **Subnet** → **Security Lists** → *Default Security List* → **Add Ingress Rules**:

| Source CIDR | Protocol | Destination port |
|---|---|---|
| `0.0.0.0/0` | TCP | `80` |
| `0.0.0.0/0` | TCP | `443` |
| `0.0.0.0/0` | UDP | `443` (optional, for HTTP/3) |

Leave SSH (22) as it is. Do **not** open 3306 (database) or 8080 (WebSockets); they stay private.

**b) The server's own firewall** (Oracle's Ubuntu image blocks everything else by default). After you SSH in (next step), run:
```bash
sudo iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
sudo iptables -I INPUT 1 -p udp --dport 443 -j ACCEPT
sudo apt-get install -y iptables-persistent      # answer "Yes" to save current rules
sudo netfilter-persistent save
```
Skipping (b) is the #1 reason "the site won't load" on Oracle.

## 4. Get a domain and point it at the server
1. Oracle Console → **Networking → IP management → Reserved public IPs** → reserve one and attach it to the instance's VNIC, so your IP never changes. (An unattached reserved IP can cost money; attach it right away.)
2. At your domain registrar (~$12/year), add an **A record**: `kusina.yourdomain.com → <your IP>`.
3. No domain yet? A free hostname like [DuckDNS](https://www.duckdns.org) works for testing. Stripe and real customers are better served by your own domain.

Check it worked: `ping kusina.yourdomain.com` shows your IP.

## 5. Connect and prepare the server
From your computer (Windows: PowerShell or Windows Terminal):
```bash
ssh -i path/to/your-private-key ubuntu@<your-IP>
```
Then on the server:
```bash
sudo apt-get update && sudo apt-get -y upgrade
sudo apt-get install -y unattended-upgrades git
sudo dpkg-reconfigure -plow unattended-upgrades          # enable automatic security updates

# A little swap protects against out-of-memory crashes
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```
(Do the firewall commands from section 3b now if you haven't.)

## 6. Install Docker
```bash
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER
exit        # log out, then SSH in again so the group change applies
```
Test: `docker run --rm hello-world`.

## 7. Get the code and configure
```bash
git clone https://github.com/ayse0dev/Kusina-Ordering-App.git
cd Kusina-Ordering-App
cp .env.production.example .env.production
nano .env.production
```
(If the repo is private, create a GitHub **personal access token** with read access and use it as the password when cloning, or add a deploy key.)

Fill in (see `DEPLOY.md` section 2 for how to generate each value):
`DOMAIN`, `APP_URL` (`https://` + your domain), `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, restaurant address/phone/email. Then `chmod 600 .env.production`.

## 8. Launch
```bash
docker compose --env-file .env.production up -d --build
docker compose --env-file .env.production logs -f web      # Ctrl+C to leave; wait for migrations to finish
docker compose --env-file .env.production ps                # web should become "healthy"
```
The first build on ARM takes several minutes. Caddy fetches your HTTPS certificate automatically as soon as the domain points at the server and ports 80/443 are open.

Create your login and test:
```bash
docker compose --env-file .env.production exec web php artisan kusina:admin you@example.com --name="Your Name"
```
Open `https://your-domain`, then run through the smoke test in `DEPLOY.md` section 7 (including a second browser to confirm live order updates).

## 9. Keep it alive (free-tier survival)
- **Idle reclamation:** Oracle's terms allow reclaiming *idle* Always Free compute instances (very low CPU/network use over a period). A quiet restaurant site could look idle. **Read Oracle's current policy**, and consider upgrading the account to **Pay As You Go**, which reportedly protects against reclamation while resources within the Always Free limits remain free. If you upgrade, **set a budget alert** (Console → Billing → Budgets, e.g. $1) so you're warned about any charge.
- **Capacity/limit changes:** Oracle has changed the free allowance before. Re-check your instance's shape stays within the Always Free limits.
- **Don't rely on this one server:** keep backups elsewhere (next section) and keep your code in GitHub, so you can rebuild on any host in about an hour.

## 10. Backups (set this up on day one)
The repo includes `scripts/backup.sh` (database + uploaded photos, keeps 14 days, verifies the files).
```bash
chmod +x scripts/backup.sh
./scripts/backup.sh            # run once by hand and check it prints "done"
```
**Copy backups off the server.** Free options: Backblaze B2 (10 GB free), Google Drive, or Oracle Object Storage. With [rclone](https://rclone.org/):
```bash
sudo apt-get install -y rclone
rclone config                  # create a remote, e.g. named "b2"
```
Then schedule it (`crontab -e`) to run every night at 03:15 and upload:
```
15 3 * * * RCLONE_REMOTE=b2:kusina-backups /home/ubuntu/Kusina-Ordering-App/scripts/backup.sh >> /home/ubuntu/backup.log 2>&1
```
**Test a restore once** (`DEPLOY.md` section 6) so you know it works before you need it.

## 11. Updating and day-to-day
```bash
cd ~/Kusina-Ordering-App && git pull && docker compose --env-file .env.production up -d --build
docker compose --env-file .env.production logs -f --tail=100 web queue reverb
```

## 12. Security checklist for this server
- SSH key login only (the Oracle image already disables passwords). Never share the private key.
- Only ports 22, 80, 443 open; database and WebSocket ports stay closed.
- Automatic security updates on (section 5), plus `sudo apt-get update && sudo apt-get upgrade` monthly.
- `.env.production` is `chmod 600`, never committed, and rotated if ever exposed.
- Optional: `sudo apt-get install -y fail2ban` to block repeated SSH guessing.

## Troubleshooting
| Symptom | Likely cause |
|---|---|
| Site times out | Firewall: re-check **both** the VCN rules (3a) and the `iptables` rules (3b). |
| Browser says certificate error / Caddy can't get a certificate | The domain's A record doesn't point at this server yet, or port 80/443 is blocked. `docker compose ... logs caddy` |
| `exec format error` on build/run | An image without ARM support. All images used here support ARM; report the exact line. |
| Build killed / out of memory | Add swap (section 5) and retry; try `docker compose build` before `up`. |
| Anything else | `DEPLOY.md` section 8. |
