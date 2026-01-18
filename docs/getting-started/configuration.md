---
title: Configuration
parent: Getting Started
nav_order: 2
---

# Configuration

TryPost is configured through environment variables in the `.env` file.

## Basic Configuration

```env
APP_NAME="TryPost"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Your application name |
| `APP_ENV` | Environment: `local`, `staging`, `production` |
| `APP_DEBUG` | Enable debug mode (set to `false` in production) |
| `APP_URL` | Your application URL |

## Self-Hosted Mode

```env
SELF_HOSTED=true
```

When `SELF_HOSTED=true`, TryPost skips payment/subscription requirements. This is the default for self-hosted installations.

## Database

TryPost supports PostgreSQL and MySQL.

### PostgreSQL (recommended)

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=trypost
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### MySQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trypost
DB_USERNAME=root
DB_PASSWORD=your_password
```

## Redis

Redis is required for queues and caching.

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## File Storage

TryPost supports local storage and S3-compatible cloud storage.

### Local Storage (default)

```env
FILESYSTEM_DISK=local
```

### AWS S3

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
AWS_URL=https://your-bucket.s3.amazonaws.com
```

### Cloudflare R2

```env
FILESYSTEM_DISK=r2
R2_ACCESS_KEY_ID=your_key
R2_SECRET_ACCESS_KEY=your_secret
R2_ENDPOINT=https://your-account.r2.cloudflarestorage.com
R2_REGION=auto
R2_BUCKET=your_bucket
R2_URL=https://your-custom-domain.com
```

### Other S3-Compatible Storage

Any S3-compatible storage (MinIO, DigitalOcean Spaces, etc.) can be used with the `s3` disk configuration.

## Mail

Configure your mail driver for sending emails (invites, notifications).

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Social Platforms

Each social platform requires API credentials. See the [Platforms documentation](../platforms/README.md) for setup instructions.

## Next Steps

- [Connect your social accounts](../platforms/README.md)
- [Create your first post](first-steps.md)
