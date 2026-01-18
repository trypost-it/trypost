# Server Requirements

This guide covers the requirements for self-hosting TryPost.

## Minimum Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 1 core | 2+ cores |
| RAM | 1 GB | 2+ GB |
| Storage | 10 GB | 20+ GB (depends on media storage) |

## Software Requirements

### Required

- **PHP 8.2+** with extensions:
 - BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
 - GD or Imagick (for image processing)
 - Redis extension
- **Composer** 2.x
- **Node.js** 18+ and npm
- **PostgreSQL** 14+ or **MySQL** 8+
- **Redis** 6+

### Optional

- **Nginx** or **Apache** (for production)
- **Supervisor** (for queue workers)
- **SSL certificate** (Let's Encrypt recommended)

## PHP Configuration

Recommended `php.ini` settings:

```ini
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 256M
max_execution_time = 300
```

## Supported Operating Systems

TryPost can run on any OS that supports the required software:

- Ubuntu 22.04+ (recommended)
- Debian 11+
- CentOS/RHEL 8+
- macOS (for development)
- Windows with WSL2 (for development)

## Cloud Providers

TryPost works well on:

- DigitalOcean Droplets
- AWS EC2
- Google Cloud Compute
- Hetzner Cloud
- Linode
- Vultr

## Storage Considerations

Media files (images, videos) can be stored:

- **Locally** - On the server's disk
- **S3** - Amazon S3
- **R2** - Cloudflare R2
- **Any S3-compatible** - MinIO, DigitalOcean Spaces, etc.

For production with heavy media usage, cloud storage is recommended.

## Next Steps

- [Production Setup](production.md)
- [Docker Deployment](docker.md)
