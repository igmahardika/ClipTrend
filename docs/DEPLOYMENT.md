# Deployment Guide — ClipTrend AI V7

## Server packages

```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-mysql php8.3-sqlite3 ffmpeg redis-server supervisor nginx unzip git
```

## App setup

```bash
git clone <repo> cliptrend
cd cliptrend
cp .env.production.example .env
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Worker with Supervisor

Example `/etc/supervisor/conf.d/cliptrend-worker.conf`:

```ini
[program:cliptrend-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cliptrend/artisan queue:work redis --queue=analysis,rendering,default --timeout=7200 --tries=3
numprocs=2
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/cliptrend/storage/logs/worker.log
stopwaitsecs=7200
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start cliptrend-worker:*
```

## Production notes

- Use Redis for queue/cache/session.
- Use S3-compatible storage for scale.
- Keep uploaded media private.
- Run FFmpeg workers on compute nodes with enough CPU.
- Never put OpenAI keys in source code.
- Use separate queue workers for analysis/rendering if load grows.
