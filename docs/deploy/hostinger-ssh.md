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

If **product images** return 403 at `/storage/products/...`, see [§3a Backblaze B2 uploads](#3a-backblaze-b2-uploads-production) (recommended) or the [local disk checklist](#image-403-troubleshooting) below.

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

## 3a) Backblaze B2 uploads (production)

**Recommended for production.** Product and storefront images are stored in Backblaze B2 (S3-compatible), not on the Hostinger disk. Deploys (`git reset --hard`) cannot delete them, and you do not need the `public/storage` symlink.

### B2 bucket setup

1. In [Backblaze B2](https://www.backblaze.com/b2/cloud-storage.html), create a bucket (e.g. `tryino-ecom-public`).
2. **Bucket Settings** → **Files in bucket are** → **Public**.
3. **App Keys** → create an Application Key with read/write access to that bucket. Save the `keyID` and `applicationKey`.

### Production `.env`

Add to the server `.env` (see [`.env.example`](../../.env.example)):

```env
PUBLIC_DISK_DRIVER=s3
AWS_ACCESS_KEY_ID=<B2 keyID>
AWS_SECRET_ACCESS_KEY=<B2 applicationKey>
AWS_DEFAULT_REGION=us-west-004
AWS_BUCKET=tryino-ecom-public
AWS_ENDPOINT=https://s3.us-west-004.backblazeb2.com
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=https://cdn.tryinotech.cloud
```

Replace the region/bucket/endpoint with your B2 values. Use the **Cloudflare CDN URL** for `AWS_URL` when CDN is configured (below); until then you can use the B2 S3 endpoint URL.

Then:

```bash
php artisan config:clear
php artisan config:cache
```

New admin uploads go straight to B2. `deploy.sh` skips `storage:link` when `PUBLIC_DISK_DRIVER=s3`.

### Cloudflare CDN (recommended for speed)

Raw B2 URLs can be slow for Pakistan visitors. Put **Cloudflare** in front of the bucket for edge caching and free B2 egress:

1. Follow [Backblaze: Deliver public B2 content through Cloudflare CDN](https://www.backblaze.com/docs/cloud-storage-deliver-public-backblaze-b2-content-through-cloudflare-cdn).
2. Create a DNS record, e.g. `cdn.tryinotech.cloud`, pointing at the bucket.
3. Set `AWS_URL=https://cdn.tryinotech.cloud` in production `.env` and run `php artisan config:cache`.

All product/hero image URLs will use the CDN hostname. This is SEO-safe and improves LCP (Core Web Vitals).

Optional: in Cloudflare, add a **Cache Rule** for `cdn.tryinotech.cloud/*` with a long edge TTL — product WebP variants have stable paths.

### Migrate existing local files to B2

If any uploads remain under `storage/app/public/` on the server:

```bash
cd ~/apps/tryino-ecom
php artisan storage:migrate-public-disk --dry-run   # preview
php artisan storage:migrate-public-disk             # upload (skips existing keys)
```

Re-upload any images that were already lost from disk via the admin panel. DB paths stay relative (`products/foo.jpg`); no DB migration needed.

### Verify B2 + CDN

```bash
php artisan tinker --execute="echo config('filesystems.disks.public.driver');"
php artisan tinker --execute="echo Storage::disk('public')->url('products/test.jpg');"
curl -I "https://cdn.tryinotech.cloud/products/<filename>.jpg"
```

Upload a product image in admin, deploy again (`bash scripts/hostinger/pull-deploy.sh`), confirm the image still loads.

### Image 403 troubleshooting

| Symptom | `PUBLIC_DISK_DRIVER` | Check |
|---------|---------------------|-------|
| 403 on `tryinotech.cloud/storage/...` | `local` (or unset) | `ls -la public/storage` (symlink?), files in `storage/app/public/products/` |
| 403 on CDN/B2 URL | `s3` | Bucket is public; `AWS_URL` matches CDN; object exists in B2 console |
| Images gone after deploy | `local` | Switch to B2 (above) — local gitignored files can be wiped on re-clone |
| Images gone after deploy | `s3` | Should not happen; check B2 bucket and credentials |

**Never run `git clean -fdx` on production** — it deletes gitignored uploads when using the local disk.

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
| GitHub deploy (`deploy:run-pending` when webhook queued) | Every minute |
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

### Deploy troubleshooting

The GitHub webhook only **queues** deploy (writes `storage/framework/deploy-pending.json` and a line to `storage/logs/deploy.log`). The actual `pull-deploy.sh` runs from **`deploy:run-pending`** via the scheduler (every minute).

If `deploy.log` is missing after a push:

1. Confirm cron runs `php artisan schedule:run` every minute.
2. SSH: `php artisan deploy:run-pending` (runs deploy immediately if pending).
3. Or: `bash scripts/hostinger/pull-deploy.sh`

Check `storage/logs/deploy.log` and `storage/logs/laravel.log` for `deploy.run_pending.*` entries.

## 9) Production admin login

Deploy runs `migrate` only — it does **not** seed users. There is no default production password in the repo.

Create or reset a staff account over SSH:

```bash
cd ~/apps/tryino-ecom
php artisan admin:create-user you@yourdomain.com --name="Your Name"
# Enter a strong password when prompted (min 8 characters)
```

Sign in at `https://tryinotech.cloud/admin/login` (not the customer `/login` page).

Local/dev only (after `php artisan db:seed`): `admin@tryino.test` / `password`.

If sign-in fails silently or shows “Page Expired”, hard-refresh the login page (stale CSRF from browser cache) and try again.

## 10) Rollback (manual, basic)

On Hostinger:

```bash
cd ~/apps/tryino-ecom
git log --oneline -n 20
git reset --hard <previous_commit_sha>
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

