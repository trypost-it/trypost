---
title: X (Twitter)
parent: Platforms
nav_order: 2
---

# X (Twitter)

Connect your X (formerly Twitter) account to TryPost.

## Create an X Developer App

1. Go to the [X Developer Portal](https://developer.twitter.com/)
2. Sign up for a developer account if you haven't already
3. Create a new project and app
4. Select the appropriate access level (Free tier works for basic posting)

## Configure OAuth 2.0

1. Go to your app's **Settings**
2. Under **User authentication settings**, click **Set up**
3. Configure:
 - **App permissions**: Read and Write
 - **Type of App**: Web App
 - **Callback URL**: `https://your-domain.com/accounts/x/callback`
 - **Website URL**: Your domain
4. Save your **Client ID** and **Client Secret**

## Environment Variables

Add to your `.env` file:

```env
X_CLIENT_ID=your_client_id
X_CLIENT_SECRET=your_client_secret
X_CLIENT_REDIRECT="${APP_URL}/accounts/x/callback"
```

## Connect Your Account

1. In TryPost, go to **Accounts**
2. Click **Connect X**
3. Authorize the app on X
4. You're ready to post!

## Supported Features

- ✅ Text posts (tweets)
- ✅ Image posts (up to 4 images)
- ✅ Video posts
- ✅ Threads (multiple connected tweets)

## Rate Limits

X has rate limits depending on your access level:

| Tier | Posts per month |
|------|-----------------|
| Free | 1,500 |
| Basic | 3,000 |
| Pro | 300,000 |

Consider upgrading your X developer tier if you need higher limits.
