# Pinterest

Connect your Pinterest account to TryPost.

## Create a Pinterest App

1. Go to [Pinterest Developers](https://developers.pinterest.com/)
2. Click **My Apps** > **Create app**
3. Fill in the app details:
 - App name
 - Description
 - Website URL
4. Submit for approval

## Configure OAuth

1. In your app settings, add the redirect URL:
 - `https://your-domain.com/accounts/pinterest/callback`
2. Note your **App ID** and **App Secret**

## Request Access

Pinterest requires app approval for production use. You can use sandbox mode for testing:

```env
PINTEREST_SANDBOX=true
```

Set to `false` once your app is approved.

## Environment Variables

Add to your `.env` file:

```env
PINTEREST_SANDBOX=true
PINTEREST_CLIENT_ID=your_app_id
PINTEREST_CLIENT_SECRET=your_app_secret
PINTEREST_CLIENT_REDIRECT="${APP_URL}/accounts/pinterest/callback"
```

## Connect Your Account

1. In TryPost, go to **Accounts**
2. Click **Connect Pinterest**
3. Log in and authorize the app
4. You're ready to post!

## Supported Features

- ✅ Pin creation
- ✅ Image pins
- ✅ Video pins
- ✅ Board selection
- ✅ Titles and descriptions
- ✅ Link URLs

## Limitations

- Idea Pins (multi-page) are not supported via API
- Story Pins are not supported
