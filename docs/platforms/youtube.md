---
title: YouTube
parent: Platforms
nav_order: 6
---

# YouTube

Connect your YouTube channel to TryPost.

## Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **YouTube Data API v3**

## Configure OAuth Consent

1. Go to **APIs & Services** > **OAuth consent screen**
2. Select **External** user type
3. Fill in the app information:
 - App name
 - User support email
 - Developer contact email
4. Add scopes:
 - `youtube.upload`
 - `youtube.readonly`
5. Add test users if in testing mode

## Create OAuth Credentials

1. Go to **APIs & Services** > **Credentials**
2. Click **Create Credentials** > **OAuth client ID**
3. Select **Web application**
4. Add your redirect URI: `https://your-domain.com/accounts/youtube/callback`
5. Note your **Client ID** and **Client Secret**

## Environment Variables

Add to your `.env` file:

```env
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
GOOGLE_CLIENT_REDIRECT="${APP_URL}/accounts/youtube/callback"
```

## Connect Your Channel

1. In TryPost, go to **Accounts**
2. Click **Connect YouTube**
3. Log in with Google and authorize
4. Select your YouTube channel
5. You're ready to post!

## Supported Features

- ✅ Video uploads
- ✅ Titles and descriptions
- ✅ Tags
- ✅ Privacy settings (public, unlisted, private)
- ✅ Scheduled publishing

## Limitations

- YouTube Shorts are uploaded as regular videos
- Live streaming is not supported
- Community posts are not supported
