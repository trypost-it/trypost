---
title: Threads
parent: Platforms
nav_order: 7
---

# Threads

Connect your Threads account to TryPost.

## Requirements

- Threads account linked to Instagram
- Meta Developer App
- Instagram Business or Creator account

## Create a Meta App

If you haven't already, follow the [Facebook setup guide](facebook.md) to create a Meta app.

## Configure Threads API

1. In your Meta app dashboard, add the **Threads API** product
2. Configure the required permissions

## Configure Permissions

Request these permissions in **App Review**:

- `threads_basic` - Basic account info
- `threads_content_publish` - To publish content

## Environment Variables

Add to your `.env` file:

```env
THREADS_CLIENT_ID=your_app_id
THREADS_CLIENT_SECRET=your_app_secret
THREADS_CLIENT_REDIRECT="${APP_URL}/accounts/threads/callback"
```

## Connect Your Account

1. In TryPost, go to **Accounts**
2. Click **Connect Threads**
3. Log in and authorize the app
4. You're ready to post!

## Supported Features

- ✅ Text posts
- ✅ Image posts
- ✅ Video posts
- ✅ Link posts

## Limitations

- Account must be linked to Instagram
- Some features may require app review approval
