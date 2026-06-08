# Hostinger shared hosting deploy (SSH) — basic CI/CD

This project can be deployed to Hostinger shared hosting using **GitHub Actions + SSH**.

## 1) Hostinger: choose an app path + set document root

Pick a folder for the app on the server:
- Preferred: `~/apps/tryino-ecom/`
- Fallback: `~/public_html/tryino-ecom/`

Set your domain's **Document Root** to Laravel's `public/` directory:
- Preferred: `~/apps/tryino-ecom/public`
- Fallback: `~/public_html/tryino-ecom/public`

### If you cannot change Document Root (403 on `/`, works only on `/public`)

Clone/deploy the **full Laravel repo** into `public_html` (or your domain folder). The repo includes a **root** [`.htaccess`](../../.htaccess) that:

- routes all requests into `public/` internally
- 301-redirects `/public/...` and `/public/index.php` to `/...`

After deploy, open `https://your-domain/` (not `/public`). If you still see 403, confirm `mod_rewrite` is enabled and that `public/.htaccess` exists.

If **product images** return 403 at `/storage/products/...`, ensure the root `.htaccess` does not forbid `/storage/` (uploaded files are served via `public/storage`). Run `php artisan storage:link` and confirm files exist under `storage/app/public/products/`.

## 2) Hostinger: allow GitHub repo access (deploy key)

On Hostinger (SSH):

```bash
ssh-keygen -t ed25519 -C "tryino-ecom-deploy" -f ~/.ssh/tryino_ecom_deploy -N ""
cat ~/.ssh/tryino_ecom_deploy.pub
```

On GitHub:
- Repo → **Settings** → **Deploy keys** → **Add deploy key**
- Paste the public key content.
- Keep it **read-only** (recommended).

## 3) Hostinger: first checkout + app bootstrap

```bash
mkdir -p ~/apps
cd ~/apps
git clone git@github.com:<owner>/<repo>.git tryino-ecom
cd tryino-ecom
```

Create `.env` on the server (do **not** commit):
- Set `APP_ENV=production`, `APP_DEBUG=false`
- Set **`APP_URL=https://tryinotech.cloud`** (no trailing slash; must match your live domain — wrong value breaks image URLs)
- Set DB credentials, mail, and any 3rd party keys.

After changing `APP_URL`:

```bash
php artisan config:clear
php artisan config:cache
php artisan storage:normalize-paths
```

Then:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache
```

Ensure Hostinger can write:
- `storage/`
- `bootstrap/cache/`

## 4) Hostinger: create `deploy.sh`

Copy the repo template from `scripts/hostinger/deploy.sh` into your server project root as `deploy.sh`:

```bash
cd ~/apps/tryino-ecom
cp scripts/hostinger/deploy.sh ./deploy.sh
chmod +x ./deploy.sh
```

Run once manually:

```bash
./deploy.sh
```

## 5) GitHub: set secrets for Actions deploy

Repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

Required:
- `HOSTINGER_SSH_HOST`
- `HOSTINGER_SSH_PORT` (usually `22`)
- `HOSTINGER_SSH_USER`
- `HOSTINGER_SSH_KEY` (private key contents)
- `HOSTINGER_APP_PATH` (example: `/home/<user>/apps/tryino-ecom`)

Private key value:

```bash
cat ~/.ssh/tryino_ecom_deploy
```

## 6) Build frontend assets (Vite) — required

Laravel expects `public/build/manifest.json`. **`public/build` is committed to git** so Hostinger `git pull` deploys it (Hostinger SSH has no `npm`).

Before you push (when JS/CSS/Vue changed):

```bash
npm install
npm run build
git add public/build
git commit -m "Build frontend assets"
git push
```

`npm run build` builds shop + admin. `npm run build:store` is storefront-only (smaller, no admin bundle).

On the server after pull, confirm:

```bash
ls -la public/build/manifest.json
```

### Optional: GitHub Actions

The workflow can still run `npm run build` and SCP `public/build` before SSH deploy (redundant if build is already in git). Ensure secrets are set (section 5).

## 7) Deploy

Push to `master`. GitHub Actions will:
- install PHP dependencies and run tests
- run `npm run build` and upload `public/build` to the server
- SSH into Hostinger and run `bash ./deploy.sh`

## 8) Scheduler (required)

Set **one** Hostinger cron entry (adjust the path):

```cron
* * * * * cd /home/<user>/apps/tryino-ecom && php artisan schedule:run >> /dev/null 2>&1
```

`schedule:run` handles everything defined in `routes/console.php`, including:

| What | How often |
|------|-----------|
| Queue worker (`queue:work --stop-when-empty`) | Every minute |
| Shipping tracking sync | Every 30 minutes |
| Search index backfill (`--missing` only) | Daily 03:30 |
| Product image WebP variants | Daily 04:00 |
| Hero image WebP variants | Daily 04:15 |
| Failed booking reconciliation | Every 15 minutes |
| Notification retries | Every 10 minutes |
| COD reconciliation | Hourly |
| WhatsApp pickup notices | Hourly |
| WhatsApp scheduled campaigns | Every minute |
| Prune old failed queue jobs | Weekly (Monday 04:00) |

Do **not** add separate cron lines for these commands — only `schedule:run`.

Full deploy still runs `catalog:rebuild-search-index` (all products) via `deploy.sh`.

## 9) Rollback (manual, basic)

On Hostinger:

```bash
cd ~/apps/tryino-ecom
git log --oneline -n 20
git reset --hard <previous_commit_sha>
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

