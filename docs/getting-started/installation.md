# Installation

This guide will walk you through installing TryPost on your server.

## Requirements

- PHP 8.2 or higher
- Node.js 18 or higher
- PostgreSQL 14+ or MySQL 8+
- Redis
- Composer

## Quick Install

### 1. Clone the repository

```bash
git clone https://github.com/trypost-it/trypost.git
cd trypost
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node.js dependencies

```bash
npm install
```

### 4. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit the `.env` file with your database credentials and other settings. See [Configuration](configuration.md) for details.

### 5. Run database migrations

```bash
php artisan migrate
```

### 6. Build frontend assets

```bash
npm run build
```

### 7. Start the application

For development:

```bash
# Terminal 1 - Laravel server
php artisan serve

# Terminal 2 - Queue worker
php artisan horizon

# Terminal 3 - Vite dev server (optional, for hot reload)
npm run dev
```

For production, configure your web server (Nginx, Apache) to serve the application.

## Verify Installation

Visit `http://localhost:8000` (or your configured domain) and you should see the TryPost welcome page.

## Next Steps

- [Configure your environment](configuration.md)
- [Connect your first social account](../platforms/README.md)
