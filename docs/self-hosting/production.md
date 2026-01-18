---
title: Production Setup
parent: Self-Hosting
nav_order: 2
---

# Production Setup

This guide covers deploying TryPost to a production environment.

## Environment Configuration

Set these values in your `.env` for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

SELF_HOSTED=true
```

## Optimizations

Run these commands after deployment:

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

## Queue Worker

TryPost uses queues for background processing. Use Supervisor to keep the queue worker running.

### Install Supervisor

```bash
# Ubuntu/Debian
sudo apt install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

### Configure Supervisor

Create `/etc/supervisor/conf.d/trypost-worker.conf`:

```ini
[program:trypost-horizon]
process_name=%(program_name)s
command=php /var/www/trypost/artisan horizon
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/trypost/storage/logs/horizon.log
stopwaitsecs=3600
```

Then start it:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start trypost-horizon
```

## Nginx Configuration

Example Nginx configuration:

{% raw %}
```nginx
server {
  listen 80;
  listen [::]:80;
  server_name your-domain.com;
  return 301 https://$server_name$request_uri;
}

server {
  listen 443 ssl http2;
  listen [::]:443 ssl http2;
  server_name your-domain.com;

  root /var/www/trypost/public;
  index index.php;

  ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

  add_header X-Frame-Options "SAMEORIGIN";
  add_header X-Content-Type-Options "nosniff";

  charset utf-8;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location = /favicon.ico { access_log off; log_not_found off; }
  location = /robots.txt  { access_log off; log_not_found off; }

  error_page 404 /index.php;

  location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
  }

  location ~ /\.(?!well-known).* {
    deny all;
  }

  client_max_body_size 100M;
}
```
{% endraw %}

## SSL Certificate

Use Let's Encrypt for free SSL:

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

## Scheduled Tasks

Add the Laravel scheduler to cron:

```bash
crontab -e
```

Add this line:

```
* * * * * cd /var/www/trypost && php artisan schedule:run >> /dev/null 2>&1
```

## File Permissions

Set correct permissions:

```bash
sudo chown -R www-data:www-data /var/www/trypost
sudo chmod -R 755 /var/www/trypost
sudo chmod -R 775 /var/www/trypost/storage
sudo chmod -R 775 /var/www/trypost/bootstrap/cache
```

## Monitoring

Consider setting up monitoring for:

- Server health (CPU, memory, disk)
- Application errors (Laravel logs)
- Queue status (Horizon dashboard at `/horizon`)

## Backups

Regularly backup:

- Database
- `.env` file
- Uploaded media (if using local storage)
