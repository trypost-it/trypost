---
title: Facebook
parent: Platforms
nav_order: 3
---

# Facebook

Connect your Facebook pages to TryPost.

## Create a Meta App

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Click **My Apps** > **Create App**
3. Select **Business** as the app type
4. Fill in the app details and create the app

## Configure Facebook Login

1. In your app dashboard, add the **Facebook Login** product
2. Go to **Facebook Login** > **Settings**
3. Add your redirect URL: `https://your-domain.com/accounts/facebook/callback`
4. Set **Client OAuth Login** to Yes
5. Set **Web OAuth Login** to Yes

## Configure Permissions

Go to **App Review** > **Permissions and Features** and request:

- `pages_show_list` - To list your pages
- `pages_read_engagement` - To read page data
- `pages_manage_posts` - To create posts

> **Note:** Some permissions require app review for production use.

## Get Your Credentials

1. Go to **Settings** > **Basic**
2. Note your **App ID** and **App Secret**

## Environment Variables

Add to your `.env` file:

```env
FACEBOOK_CLIENT_ID=your_app_id
FACEBOOK_CLIENT_SECRET=your_app_secret
FACEBOOK_CLIENT_REDIRECT="${APP_URL}/accounts/facebook/callback"
```

## Connect Your Page

1. In TryPost, go to **Accounts**
2. Click **Connect Facebook**
3. Log in and authorize the app
4. Select the page(s) you want to connect
5. You're ready to post!

## Supported Features

- ✅ Text posts
- ✅ Image posts
- ✅ Multi-image posts
- ✅ Video posts
- ✅ Link posts
- ✅ Page posting

> **Note:** Personal profile posting is not supported due to Meta API limitations. Only pages can be connected.
