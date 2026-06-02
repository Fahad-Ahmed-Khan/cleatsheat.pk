# Hostinger shared hosting deploy (SSH) — basic CI/CD

This project can be deployed to Hostinger shared hosting using **GitHub Actions + SSH**.

## 1) Hostinger: choose an app path + set document root

Pick a folder for the app on the server:
- Preferred: `~/apps/tryino-ecom/`
- Fallback: `~/public_html/tryino-ecom/`

Set your domain's **Document Root** to Laravel's `public/` directory:
- Preferred: `~/apps/tryino-ecom/public`
- Fallback: `~/public_html/tryino-ecom/public`

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
- Set `APP_URL`, DB credentials, mail, and any 3rd party keys.

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

## 6) Deploy

Push to `main`. GitHub Actions will:
- install dependencies
- run `php artisan test`
- SSH into Hostinger and run `bash ./deploy.sh`

## 7) Scheduler (optional)

If you use Laravel Scheduler, set a Hostinger cron:

```cron
* * * * * php /home/<user>/apps/tryino-ecom/artisan schedule:run >> /dev/null 2>&1
```

## 8) Rollback (manual, basic)

On Hostinger:

```bash
cd ~/apps/tryino-ecom
git log --oneline -n 20
git reset --hard <previous_commit_sha>
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

