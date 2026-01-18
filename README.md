<p align="center">
  <img src="public/images/trypost/logo-dark.png" alt="TryPost" width="200">
</p>

<h1 align="center">TryPost</h1>

<p align="center">
  <strong>The open-source social media scheduling platform</strong>
</p>

<p align="center">
  Schedule, manage, and publish content to all your social media accounts from one place.
  <br />
  Self-hosted. Privacy-focused. No limits.
</p>

<p align="center">
  <a href="https://github.com/trypost-it/trypost/stargazers"><img src="https://img.shields.io/github/stars/trypost-it/trypost" alt="Stars"></a>
  <a href="https://github.com/trypost-it/trypost/blob/main/LICENSE.md"><img src="https://img.shields.io/badge/license-FSL-blue" alt="License"></a>
  <a href="https://github.com/trypost-it/trypost/releases"><img src="https://img.shields.io/github/v/release/trypost-it/trypost" alt="Release"></a>
</p>

<p align="center">
  <a href="#features">Features</a> •
  <a href="#supported-platforms">Platforms</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#self-hosting">Self-Hosting</a> •
  <a href="#contributing">Contributing</a>
</p>

---

## Why TryPost?

Tired of paying expensive monthly fees for social media scheduling tools? Want full control over your data? TryPost is the solution.

- **100% Open Source** - Inspect the code, contribute, make it yours
- **Self-Hosted** - Your data stays on your servers
- **No Limits** - Schedule unlimited posts, connect unlimited accounts
- **Privacy First** - No tracking, no analytics, no data selling

## Features

- **Visual Calendar** - Drag and drop posts across your content calendar
- **Post Composer** - Create and preview posts for multiple platforms at once
- **Media Library** - Upload images and videos with automatic optimization
- **Team Collaboration** - Invite team members with role-based permissions
- **Workspaces** - Manage multiple brands or clients separately
- **Smart Scheduling** - Schedule posts for optimal engagement times
- **Multi-Platform Preview** - See exactly how your post will look on each platform

## Supported Platforms

<table>
  <tr>
    <td align="center"><img src="public/images/accounts/x.png" width="40"><br><b>X (Twitter)</b></td>
    <td align="center"><img src="public/images/accounts/linkedin.png" width="40"><br><b>LinkedIn</b></td>
    <td align="center"><img src="public/images/accounts/facebook.png" width="40"><br><b>Facebook</b></td>
    <td align="center"><img src="public/images/accounts/instagram.png" width="40"><br><b>Instagram</b></td>
    <td align="center"><img src="public/images/accounts/tiktok.png" width="40"><br><b>TikTok</b></td>
  </tr>
  <tr>
    <td align="center"><img src="public/images/accounts/youtube.png" width="40"><br><b>YouTube</b></td>
    <td align="center"><img src="public/images/accounts/pinterest.png" width="40"><br><b>Pinterest</b></td>
    <td align="center"><img src="public/images/accounts/threads.png" width="40"><br><b>Threads</b></td>
    <td align="center"><img src="public/images/accounts/bluesky.png" width="40"><br><b>Bluesky</b></td>
    <td align="center"><img src="public/images/accounts/mastodon.png" width="40"><br><b>Mastodon</b></td>
  </tr>
</table>

## Quick Start

### Requirements

- PHP 8.2+
- Node.js 18+
- MySQL 8.0+ or PostgreSQL 14+
- Redis
- Composer

### Installation

```bash
# Clone the repository
git clone https://github.com/trypost-it/trypost.git
cd trypost

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure your database in .env, then run migrations
php artisan migrate --seed

# Build assets
npm run build

# Start the server
php artisan serve
```

Visit `http://localhost:8000` and create your account!

## Self-Hosting

TryPost is designed to be self-hosted. Enable self-hosted mode in your `.env`:

```env
SELF_HOSTED=true
```

This skips payment/subscription requirements and gives you full access.

### Running in Production

```bash
# Start the queue worker
php artisan horizon

# Or use the watcher for development
php artisan horizon:watch
```

### Docker (Coming Soon)

```bash
docker-compose up -d
```

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 12, PHP 8.4 |
| **Frontend** | Vue 3, Inertia.js, TypeScript |
| **Styling** | Tailwind CSS 4 |
| **Database** | MySQL / PostgreSQL |
| **Queue** | Redis + Laravel Horizon |
| **Real-time** | Laravel Reverb |

## Roadmap

Check out our [public roadmap](https://github.com/orgs/trypost-it/projects/1) to see what's coming next and vote on features!

## Contributing

We love contributions! Whether it's:

- Bug reports
- Feature requests
- Documentation improvements
- Code contributions

### Development Setup

```bash
# Install dependencies
composer install
npm install

# Start development server
npm run dev

# Run tests
php artisan test
```

## Community

- [GitHub Discussions](https://github.com/trypost-it/trypost/discussions) - Ask questions, share ideas
- [GitHub Issues](https://github.com/trypost-it/trypost/issues) - Report bugs, request features

## License

TryPost is licensed under the [Functional Source License (FSL)](LICENSE.md).

**You can:**
- Use TryPost for personal or internal business use
- Self-host for your own social media management
- Modify and contribute to the codebase

**You cannot:**
- Offer TryPost as a competing SaaS product
- White-label and resell TryPost

---

<p align="center">
  <strong>If TryPost helps you, please give us a star!</strong>
  <br />
  <br />
  <a href="https://github.com/trypost-it/trypost">
    <img src="https://img.shields.io/github/stars/trypost-it/trypost?style=social" alt="Star on GitHub">
  </a>
</p>
