<p align="center">
  <img src="public/images/trypost/logo-dark.png" alt="TryPost" width="200">
</p>

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
  <a href="https://trypost.it/docs">Documentation</a> &bull;
  <a href="https://github.com/orgs/trypost-it/projects/1">Roadmap</a> &bull;
  <a href="https://github.com/trypost-it/trypost/discussions">Community</a>
</p>

---

## Why TryPost?

Tired of paying expensive monthly fees for social media scheduling tools? Want full control over your data? TryPost is the solution.

|                         |                                                      |
| ----------------------- | ---------------------------------------------------- |
| **100% Open Source** | Inspect the code, contribute, make it yours          |
| **Self-Hosted**      | Your data stays on your servers                      |
| **No Limits**        | Schedule unlimited posts, connect unlimited accounts |
| **Privacy First**    | No tracking, no analytics, no data selling           |

## Features

|                               |                                                         |
| ----------------------------- | ------------------------------------------------------- |
| **Visual Calendar**        | Drag and drop posts across your content calendar        |
| **Post Composer**          | Create and preview posts for multiple platforms at once |
| **Media Library**          | Upload images and videos with automatic optimization    |
| **Team Collaboration**     | Invite team members with role-based permissions (Owner, Admin, Member) |
| **Workspaces**             | Manage multiple brands or clients separately            |
| **REST API**               | Full API with Bearer token authentication               |
| **MCP Server**             | AI-ready with Model Context Protocol support            |
| **Google Login**           | Sign up and log in with Google OAuth                    |
| **Notifications**          | In-app and email notifications for post status          |
| **i18n**                   | Available in English, Spanish, and Portuguese           |

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

## Tech Stack

| Layer | Technology |
| ----- | ---------- |
| **Backend** | Laravel 13, PHP 8.4 |
| **Frontend** | Vue 3, Inertia.js v3, Tailwind CSS v4 |
| **Database** | PostgreSQL |
| **Queue** | Redis + Laravel Horizon |
| **WebSockets** | Laravel Reverb |
| **API** | REST with Bearer token auth |
| **MCP** | Laravel MCP for AI integrations |
| **Payments** | Laravel Cashier (Stripe) |

## Getting Started

Get TryPost running in minutes:

| | |
| --- | --- |
| [Installation Guide](https://trypost.it/docs/getting-started/installation) | Step-by-step setup |
| [Docker Setup](https://trypost.it/docs/self-hosting/docker) | Run with Laravel Sail |
| [Configuration](https://trypost.it/docs/getting-started/configuration) | Environment setup |
| [Platform Setup](https://trypost.it/docs/platforms/) | Connect your social accounts |

### Quick Start

```bash
git clone https://github.com/trypost-it/trypost.git
cd trypost
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
```

## API

TryPost includes a REST API for programmatic access. All endpoints are under `/api` and require a Bearer token.

```bash
curl -H "Authorization: Bearer tp_your_token" \
  https://your-domain.com/api/posts
```

See the [API documentation](https://trypost.it/docs/api) for all available endpoints.

## MCP Server

TryPost ships with a Model Context Protocol (MCP) server at `/mcp/trypost`, enabling AI assistants to manage your social media directly.

## Contributing

We love contributions! Check the [issues](https://github.com/trypost-it/trypost/issues) for open tasks.

- [Discussions](https://github.com/trypost-it/trypost/discussions) - Ask questions, share ideas
- [Issues](https://github.com/trypost-it/trypost/issues) - Report bugs, request features

## License

TryPost is licensed under the [Functional Source License (FSL)](LICENSE.md).

**You can:** Use for personal or business use, self-host, modify and contribute.

**You cannot:** Offer as a competing SaaS, white-label and resell.

---

<p align="center">
  <strong>If TryPost helps you, please give us a star</strong>
</p>
