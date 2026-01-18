# TikTok

Connect your TikTok account to TryPost.

## Create a TikTok Developer App

1. Go to [TikTok Developers](https://developers.tiktok.com/)
2. Log in and go to **Manage Apps**
3. Click **Create App**
4. Select **Content Posting API** as your use case
5. Fill in the app details

## Configure Your App

1. In your app settings, add the redirect URL:
 - `https://your-domain.com/accounts/tiktok/callback`
2. Request the required scopes:
 - `user.info.basic`
 - `video.upload`
 - `video.publish`
3. Submit your app for review

> **Note:** TikTok requires app approval before you can use the API in production.

## Get Your Credentials

1. Go to your app's **Manage** page
2. Note your **Client Key** and **Client Secret**

## Environment Variables

Add to your `.env` file:

```env
TIKTOK_CLIENT_ID=your_client_key
TIKTOK_CLIENT_SECRET=your_client_secret
TIKTOK_CLIENT_REDIRECT="${APP_URL}/accounts/tiktok/callback"
```

## Connect Your Account

1. In TryPost, go to **Accounts**
2. Click **Connect TikTok**
3. Log in and authorize the app
4. You're ready to post!

## Supported Features

- ✅ Video posts
- ✅ Captions with hashtags

## Requirements

- Videos must be between 1 second and 10 minutes
- Supported formats: MP4, WebM
- Maximum file size varies by account type

## Limitations

- Images cannot be posted directly (TikTok is video-only)
- Some features require a verified business account
