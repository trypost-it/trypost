---
title: LinkedIn
parent: Platforms
nav_order: 1
---

# LinkedIn

Connect your LinkedIn profile or company pages to TryPost.

## Create a LinkedIn App

1. Go to [LinkedIn Developers](https://developer.linkedin.com/)
2. Click **Create App**
3. Fill in the app details:
 - App name: `TryPost` (or your preferred name)
 - LinkedIn Page: Select or create a company page
 - App logo: Upload an image
4. Accept the terms and click **Create app**

## Configure OAuth

1. Go to the **Auth** tab
2. Add your redirect URLs:
 - `https://your-domain.com/accounts/linkedin/callback` (for profiles)
 - `https://your-domain.com/accounts/linkedin-page/callback` (for pages)
3. Note your **Client ID** and **Client Secret**

## Request API Access

1. Go to the **Products** tab
2. Request access to:
 - **Share on LinkedIn** - Required for posting
 - **Sign In with LinkedIn using OpenID Connect** - Required for authentication
 - **Advertising API** (optional) - For company page posting

> **Note:** Some products require approval and may take a few days.

## Environment Variables

Add to your `.env` file:

```env
LINKEDIN_CLIENT_ID=your_client_id
LINKEDIN_CLIENT_SECRET=your_client_secret
LINKEDIN_CLIENT_REDIRECT="${APP_URL}/accounts/linkedin/callback"
LINKEDIN_PAGE_CLIENT_REDIRECT="${APP_URL}/accounts/linkedin-page/callback"
```

## Connect Your Account

1. In TryPost, go to **Accounts**
2. Click **Connect LinkedIn** (for profile) or **Connect LinkedIn Page** (for company pages)
3. Authorize the app on LinkedIn
4. You're ready to post!

## Supported Features

- ✅ Text posts
- ✅ Image posts
- ✅ Multi-image posts
- ✅ Video posts
- ✅ Profile posting
- ✅ Company page posting
