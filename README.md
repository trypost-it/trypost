<p align="center">
  <img src="public/images/trypost/logo-dark.png" alt="TryPost" width="200">
</p>

<p align="center">
  <strong>The open-source alternative to Buffer, Hootsuite, and Later.</strong>
</p>

<p align="center">
  Schedule, write, and publish to every social network from one place.<br/>
  Self-hosted. Built for teams, agencies, and creators who own their data.
</p>

<p align="center">
  <a href="https://github.com/trypost-it/trypost/stargazers"><img src="https://img.shields.io/github/stars/trypost-it/trypost?style=flat-square&color=4f46e5" alt="Stars"></a>
  <a href="https://github.com/trypost-it/trypost/blob/main/LICENSE.md"><img src="https://img.shields.io/badge/license-AGPL--3.0-4f46e5?style=flat-square" alt="License"></a>
  <a href="https://github.com/trypost-it/trypost/releases"><img src="https://img.shields.io/github/v/release/trypost-it/trypost?style=flat-square&color=4f46e5" alt="Release"></a>
  <a href="https://github.com/trypost-it/trypost/discussions"><img src="https://img.shields.io/github/discussions/trypost-it/trypost?style=flat-square&color=4f46e5" alt="Discussions"></a>
</p>

<p align="center">
  <a href="https://trypost.it">Cloud</a> &bull;
  <a href="https://trypost.it/docs">Documentation</a> &bull;
  <a href="https://github.com/orgs/trypost-it/projects/1">Roadmap</a> &bull;
  <a href="https://github.com/trypost-it/trypost/discussions">Community</a>
</p>

---

## TryPost 1.0 is here

A massive release that turns TryPost into a complete open-source social media platform.

- **MCP Server** — connect Claude, Cursor, ChatGPT, and any MCP client. Schedule, publish, and pull metrics with natural language.
- **REST API** — Personal Access Tokens, every workflow you can do in the dashboard, available over HTTP.
- **Workspaces &amp; Teams** — manage multiple brands or clients in isolated environments. Invite team members with role-based permissions.
- **AI Carousel Generator** — type a prompt, get a multi-slide LinkedIn / Instagram carousel back, with images and copy on-brand.
- **Brand Profile** — set tone, voice, language, and colors once. Every AI generation respects them automatically.
- **Analytics** — per-account engagement metrics from every platform that exposes them, in a single dashboard.

Read the [release notes &rarr;](https://github.com/trypost-it/trypost/releases/latest)

---

## Why TryPost

Tired of paying $30–$200/month per user for a scheduler? Tired of your content sitting on someone else's database?

|                              |                                                            |
| ---------------------------- | ---------------------------------------------------------- |
| **Self-hosted, your data**   | Your posts, drafts, and metrics never leave your server    |
| **No artificial limits**     | Unlimited posts, unlimited connected accounts, unlimited workspaces |
| **AI built in**              | Captions, carousels, on-brand voice — using your own keys  |
| **Multi-tenant ready**       | Workspaces + roles for agencies managing many clients      |
| **API + MCP first-class**    | Automate everything; your AI assistant can post for you    |
| **AGPL-licensed**            | Inspect every line, fork it, ship it — forever free        |

## Features

|                                |                                                                        |
| ------------------------------ | ---------------------------------------------------------------------- |
| **Visual Calendar**            | See every scheduled post at a glance, switch between month/week/day    |
| **Multi-Platform Composer**    | One draft &rarr; preview &amp; tweak for every network in parallel    |
| **AI Generate / Review**       | Draft from a prompt, get inline feedback before you publish            |
| **AI Carousel Builder**        | Prompt &rarr; multi-slide carousel with images, on-brand               |
| **Brand Profile**              | Tone, voice, language, colors — applied to every AI call               |
| **Asset Library**              | Reusable workspace media + Unsplash &amp; Giphy search built in        |
| **Signatures &amp; Labels**    | Reusable text blocks (hashtags, CTAs) and color-coded post tags        |
| **Team Collaboration**         | Owner / Admin / Member roles, comments with @mentions on drafts        |
| **Workspaces**                 | Isolate brands, clients, or projects in their own spaces               |
| **REST API + MCP**             | Full programmatic control; AI assistants integrate natively            |
| **Analytics**                  | Per-account engagement metrics across every supported platform         |
| **Multi-language**             | English, Spanish, Portuguese                                           |

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

## Get Started

| | |
| --- | --- |
| **Try it on Cloud** | The fastest way. [Sign up at trypost.it](https://trypost.it) |
| **Self-host** | Free forever. [Installation guide &rarr;](https://trypost.it/docs/getting-started/installation) |
| **Run with AI assistants** | [Connect Claude / Cursor / ChatGPT via MCP &rarr;](https://trypost.it/docs/mcp) |

## Run with Docker (recommended for contributors)

A single command brings up the full stack — Laravel app, Postgres, Redis, Mailpit, Reverb (websockets), Horizon (queue dashboard), and the scheduler — with no host PHP or Node prerequisites:

```bash
git clone https://github.com/trypost-it/trypost.git
cd trypost
docker compose up --build
```

First boot takes ~3–4 minutes (composer install, npm ci, asset build, migrations). Subsequent boots are seconds. When the app container reports healthy, open <http://localhost:8000>.

**Mail UI** at <http://localhost:8025>. **Vite HMR** on `:5173`. **Reverb websocket** on `:8080`.

### Common commands

```bash
# Artisan, Composer, Pest
docker compose exec app php artisan tinker
docker compose exec app php artisan test --compact
docker compose exec app composer require some/package

# View logs (one stream per process)
docker compose logs -f app

# Stop the stack
docker compose down

# Wipe all data (db, redis, vendor, node_modules)
docker compose down -v
```

### Customizing your environment

Compose merges `compose.override.yaml` (gitignored, personal) on top of the committed `compose.yaml`. Copy the example and edit:

```bash
cp compose.override.yaml.example compose.override.yaml
```

Common tweaks (port forwarding, Xdebug, UID/GID alignment) are documented inline in that file.

### Troubleshooting

- **Port already in use** — set `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_UI_PORT` in your shell or `.env`, or use `compose.override.yaml`.
- **Files written by container show as root-owned (Linux)** — pass your UID/GID at build time:
  ```bash
  UID=$(id -u) GID=$(id -g) docker compose up --build
  ```
- **Vite HMR not connecting** — confirm `:5173` is forwarded and not blocked by your firewall; check `docker compose logs app` for the Vite process output.
- **First boot is stuck** — composer install + npm ci run the first time only. Watch progress with `docker compose logs -f app`. Don't Ctrl-C in the first 3–4 minutes.
- **Reset everything** — `docker compose down -v && docker compose up --build` re-runs migrations and re-seeds dependencies.

### Production image

The same `docker/Dockerfile` exposes a `production` target for self-hosters:

```bash
docker build --target production -t trypost:latest -f docker/Dockerfile .
```

The production image bakes in `composer install --no-dev`, `npm run build`, SSR build, and a hardened OpCache profile. Note that `VITE_REVERB_HOST` is baked into the JS bundle at image build time — set it for your deployment hostname before building.

## Contributing

We love contributions, no matter how small. Pick an [issue](https://github.com/trypost-it/trypost/issues), say hi in [Discussions](https://github.com/trypost-it/trypost/discussions), or just open a PR with what you'd like to see.

If TryPost is useful to you, **a star helps more people find it.** That's the most valuable contribution if you're short on time.

## License

[GNU Affero General Public License v3.0](LICENSE.md) — use, modify, fork, self-host, and redistribute, including commercially. If you run a modified version as a network service, you need to make your changes available to its users (AGPL §13).

---

<p align="center">
  Built openly by the community. <a href="https://github.com/trypost-it/trypost/stargazers">Star us on GitHub</a> and tell a friend.
</p>
