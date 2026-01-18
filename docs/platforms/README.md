# Supported Platforms

TryPost supports the following social media platforms:

| Platform | Status | API Requirements |
|----------|--------|------------------|
| [LinkedIn](linkedin.md) | ✅ Full support | OAuth App |
| [X (Twitter)](x-twitter.md) | ✅ Full support | OAuth App |
| [Facebook](facebook.md) | ✅ Full support | Meta App |
| [Instagram](instagram.md) | ✅ Full support | Meta App (Business account required) |
| [TikTok](tiktok.md) | ✅ Full support | TikTok Developer App |
| [YouTube](youtube.md) | ✅ Full support | Google Cloud Project |
| [Threads](threads.md) | ✅ Full support | Meta App |
| [Pinterest](pinterest.md) | ✅ Full support | Pinterest App |
| [Bluesky](bluesky.md) | ✅ Full support | App Password (no OAuth) |
| [Mastodon](mastodon.md) | ✅ Full support | Instance OAuth |

## General Setup

Each platform requires:

1. **Create a developer app** on the platform's developer portal
2. **Configure OAuth credentials** in your `.env` file
3. **Set the callback URL** to `{APP_URL}/accounts/{platform}/callback`

## Callback URLs

When configuring your apps, use these callback URLs (replace `https://your-domain.com` with your actual URL):

| Platform | Callback URL |
|----------|-------------|
| LinkedIn | `https://your-domain.com/accounts/linkedin/callback` |
| LinkedIn Page | `https://your-domain.com/accounts/linkedin-page/callback` |
| X (Twitter) | `https://your-domain.com/accounts/x/callback` |
| TikTok | `https://your-domain.com/accounts/tiktok/callback` |
| Facebook | `https://your-domain.com/accounts/facebook/callback` |
| Instagram | `https://your-domain.com/accounts/instagram/callback` |
| Threads | `https://your-domain.com/accounts/threads/callback` |
| YouTube | `https://your-domain.com/accounts/youtube/callback` |
| Pinterest | `https://your-domain.com/accounts/pinterest/callback` |
| Mastodon | `https://your-domain.com/accounts/mastodon/callback` |

> **Note:** Bluesky doesn't use OAuth. You'll enter your credentials directly in TryPost.

## Disabling Platforms

You can disable specific platforms by setting environment variables:

```env
TRYPOST_LINKEDIN_ENABLED=false
TRYPOST_TIKTOK_ENABLED=false
```

This hides the platform from the connection options.
