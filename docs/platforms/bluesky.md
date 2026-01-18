# Bluesky

Connect your Bluesky account to TryPost.

## No Developer App Required

Bluesky uses App Passwords instead of OAuth, so you don't need to create a developer app.

## Create an App Password

1. Log in to [Bluesky](https://bsky.app/)
2. Go to **Settings** > **App Passwords**
3. Click **Add App Password**
4. Give it a name (e.g., "TryPost")
5. Copy the generated password

> **Important:** Save this password securely. You won't be able to see it again.

## Connect Your Account

1. In TryPost, go to **Accounts**
2. Click **Connect Bluesky**
3. Enter your Bluesky handle (e.g., `yourname.bsky.social`)
4. Enter the App Password you created
5. Click **Connect**
6. You're ready to post!

## Environment Variables

No environment variables are required for Bluesky. Each user connects with their own app password.

## Supported Features

- ✅ Text posts
- ✅ Image posts (up to 4 images)
- ✅ Link cards
- ✅ Mentions
- ✅ Hashtags

## Custom PDS

If you're using a custom Personal Data Server (PDS), you can enter the full handle including your domain.

## Security

- App Passwords have limited permissions compared to your main password
- You can revoke an App Password at any time from Bluesky settings
- TryPost stores the app password securely encrypted
