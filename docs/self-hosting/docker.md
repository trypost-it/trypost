---
title: Docker
parent: Self-Hosting
nav_order: 3
---

# Docker Deployment

TryPost includes [Laravel Sail](https://laravel.com/docs/sail), a lightweight Docker development environment.

## Requirements

- Docker Desktop (Mac, Windows) or Docker Engine (Linux)
- Docker Compose

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/trypost-it/trypost.git
cd trypost
```

### 2. Install Composer dependencies

You can use a temporary container to install dependencies without PHP on your host:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 3. Configure environment

```bash
cp .env.example .env
```

Update your `.env` for Docker:

```env
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=trypost
DB_USERNAME=sail
DB_PASSWORD=password

REDIS_HOST=redis
```

### 4. Generate the Docker Compose file

```bash
php artisan sail:install
```

Select the services you need:
- **pgsql** - PostgreSQL database (recommended)
- **redis** - Required for queues and caching
- **meilisearch** - Optional, for search functionality

### 5. Start the containers

```bash
./vendor/bin/sail up -d
```

### 6. Generate application key

```bash
./vendor/bin/sail artisan key:generate
```

### 7. Run migrations

```bash
./vendor/bin/sail artisan migrate
```

### 8. Build frontend assets

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

## Accessing TryPost

Once running, access TryPost at: `http://localhost`

## Common Commands

```bash
# Start containers
./vendor/bin/sail up -d

# Stop containers
./vendor/bin/sail down

# View logs
./vendor/bin/sail logs

# Run Artisan commands
./vendor/bin/sail artisan <command>

# Run npm commands
./vendor/bin/sail npm <command>

# Access PostgreSQL
./vendor/bin/sail psql

# Access Redis CLI
./vendor/bin/sail redis
```

## Running the Queue Worker

For background job processing:

```bash
./vendor/bin/sail artisan horizon
```

Or run it in a separate terminal:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan horizon
```

## Shell Alias

Add this alias to your shell profile (`~/.bashrc`, `~/.zshrc`):

```bash
alias sail='./vendor/bin/sail'
```

Then you can use:

```bash
sail up -d
sail artisan migrate
sail npm run dev
```

## Production with Docker

For production deployments with Docker, you'll want to:

1. Use a production-ready Docker Compose configuration
2. Set `APP_ENV=production` and `APP_DEBUG=false`
3. Use proper SSL termination (nginx, Traefik, etc.)
4. Configure persistent volumes for storage
5. Set up proper logging and monitoring

See the [Production Setup](production.md) guide for more details on production configurations.

## Troubleshooting

### Port conflicts

If port 80 is already in use, change the `APP_PORT` in `.env`:

```env
APP_PORT=8080
```

### Permission issues

On Linux, you may need to run:

```bash
sudo chown -R $USER: .
```

### Database connection refused

Make sure the database container is running:

```bash
./vendor/bin/sail ps
```

Wait a few seconds after starting for the database to initialize.
