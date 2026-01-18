# Instagram

Connect your Instagram Business or Creator account to TryPost.

## Requirements

- Instagram Business or Creator account (not personal)
- Facebook Page connected to your Instagram account
- Meta Developer App

## Create a Meta App

If you haven't already, follow the [Facebook setup guide](facebook.md) to create a Meta app.

## Configure Instagram API

1. In your Meta app dashboard, add the **Instagram Graph API** product
2. Go to **Instagram** > **Basic Display** or **Instagram Graph API**

## Configure Permissions

Request these permissions in **App Review**:

- `instagram_basic` - Basic account info
- `instagram_content_publish` - To publish content
- `pages_show_list` - To access linked Facebook page

## Environment Variables

Add to your `.env` file:

```env
INSTAGRAM_CLIENT_ID=your_app_id
INSTAGRAM_CLIENT_SECRET=your_app_secret
INSTAGRAM_CLIENT_REDIRECT="${APP_URL}/accounts/instagram/callback"
```

## Connect Your Account

1. Make sure your Instagram account is:
 - Set to Business or Creator account
 - Connected to a Facebook Page
2. In TryPost, go to **Accounts**
3. Click **Connect Instagram**
4. Log in with Facebook and authorize
5. Select your Instagram account
6. You're ready to post!

## Supported Features

- ✅ Image posts
- ✅ Carousel posts (multiple images)
- ✅ Video posts (Reels)
- ✅ Captions with hashtags

## Limitations

- Personal accounts cannot be connected (Instagram API limitation)
- Stories are not supported via API
- Direct messages are not supported
